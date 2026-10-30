<?php

namespace App\LegacyDatabase;

use App\Api\ExistingApiKeyAdmission;
use App\FreshWeb\FreshWebLdapSyncProfile;
use App\Ldap\LdapSyncFileSources;
use App\Terminal\ExistingTerminalRootAdmission;
use App\Vacation\VacationSchema;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\SQLiteConnection;
use PDO;
use RuntimeException;
use Throwable;

/** Fixed-cohort offline handoff. Never starts a service, reads/rotates a key or repairs an unknown state. */
final class VacationWorkflowCohortUpgrade
{
    public function __construct(private readonly OfflineFiles $files,private readonly array $mapping,
        private readonly array $forbiddenRoots) {}

    /**
     * Explicitly stopped/drained caller only. Resume is an operator action, not an automatic retry.
     * Optional external channel metadata is validated and rebound into a NEW private output,
     * never written to an operator EnvironmentFile, and never switches a channel off.
     */
    public function run(string $directory,string $backup,bool $stopped,bool $resume=false,
        #[\SensitiveParameter] array $channels=['api'=>null,'terminal'=>null,'ldap_sync'=>null]): array
    {
        if (!$stopped) { throw new RuntimeException('Stop and drain every cohort consumer before this explicit offline operation.'); }
        $cohort=new VacationWorkflowUpgradeFiles($directory,$this->files);
        return $cohort->locked(function () use($cohort,$directory,$backup,$resume,$channels):array {
            $upgrade=new VacationWorkflowV7Upgrade($this->mapping);
            if ($cohort->exists(VacationWorkflowUpgradeFiles::RECEIPT) && !$cohort->exists(VacationWorkflowUpgradeFiles::STATE)) {
                $receipt=$cohort->json(VacationWorkflowUpgradeFiles::RECEIPT);
                if (array_keys($receipt)!==['version','plan','completed_profile'] || $receipt['version']!==1
                    || $receipt['completed_profile']!=='vacation-requests-v7' || !is_array($receipt['plan'])) {
                    throw new RuntimeException('Unknown completion record requires private inspection.');
                }
                $plan=$this->validatePlan($receipt['plan'],$directory,$backup,$channels);
                $this->confirmCompleted($cohort,$upgrade,$plan);
                return $this->result(false);
            }
            if ($cohort->exists(VacationWorkflowUpgradeFiles::STATE)) {
                if (!$resume) { throw new RuntimeException('A pending upgrade requires explicit offline --resume after inspection.'); }
                $plan=$this->validatePlan($cohort->json(VacationWorkflowUpgradeFiles::STATE),$directory,$backup,$channels);
            } else {
                if ($resume || $cohort->exists(VacationWorkflowUpgradeFiles::RECEIPT)
                    || $cohort->exists('vacation-v7-channel-admissions.json')) {
                    throw new RuntimeException('Only a new upgrade or its exact pending forward resume is supported.');
                }
                $plan=$this->prepare($cohort,$upgrade,$directory,$backup,$channels);
                // Fence is first. A crash or unknown outcome retains it and blocks new Runtime opens.
                $cohort->reserve(VacationWorkflowUpgradeFiles::STATE,VacationWorkflowUpgradeFiles::encode($plan));
            }
            $pending=VacationWorkflowUpgradeFiles::encode($plan);
            $this->requireBackup($plan);
            $this->requireMetadataValues($cohort,$plan);
            $snapshot=$this->files->snapshot($plan['old_manifest']['path']);
            $configuration=$this->files->readWriteConfiguration($plan['old_manifest']['path'],$snapshot);
            $configuration['name']='chrono_offline_vacation_v7';
            $pdo=$connection=null;
            try {
                $pdo=(new SQLiteConnector)->connect($configuration);
                $connection=new SQLiteConnection($pdo,$plan['old_manifest']['path'],'',$configuration);
                $checked=$upgrade->inspect($pdo);
                $this->requireConnectionSettings($pdo,$checked['profile']==='account-lifecycle-v5'?$plan['old_manifest']:$plan['new_manifest'],true);
                if ($upgrade->preservedSource($pdo)!==$plan['preserved_source']) { throw new RuntimeException; }
                if ($checked['profile']==='account-lifecycle-v5' && $snapshot!==$plan['backup_evidence']['target']) {
                    throw new RuntimeException('The original stopped source changed before schema commit.');
                }
                $guard=function (PDO $opened) use($cohort,$plan,$pending):void {
                    // No raw open/close/hash of the DB inode while SQLite owns its transaction.
                    $databases=$opened->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
                    $main=array_values(array_filter($databases,static fn(array $r):bool=>$r['name']==='main'));
                    if (count($main)!==1 || $main[0]['file']!==$plan['old_manifest']['path']) { throw new RuntimeException; }
                    foreach ($databases as $database) {
                        if (!in_array($database['name'],['main','temp'],true)
                            || ($database['name']==='temp' && $database['file']!=='')) { throw new RuntimeException; }
                    }
                    if ($opened->query('SELECT COUNT(*) FROM sqlite_temp_schema')->fetchColumn()!==0) { throw new RuntimeException; }
                    $this->requireConnectionSettings($opened,$opened->query('SELECT MAX(version) FROM app_migrations')->fetchColumn()===7
                        ?$plan['new_manifest']:$plan['old_manifest'],true);
                    if (!hash_equals($cohort->read(VacationWorkflowUpgradeFiles::STATE),$pending)) { throw new RuntimeException; }
                    $this->requireMetadataValues($cohort,$plan);
                };
                $post=$upgrade->applyConnection($connection,$guard);
                $actual=$upgrade->manifest(RuntimeAdmission::fromConfiguration($plan['old_manifest']),$post);
                if ($actual!==$plan['new_manifest'] || $upgrade->preservedSource($pdo)!==$plan['preserved_source']) {
                    throw new RuntimeException;
                }
                foreach (VacationSchema::tables() as $table) {
                    if ($pdo->query('SELECT COUNT(*) FROM "'.$table['name'].'"')->fetchColumn()!==0) { throw new RuntimeException; }
                }
                $connection->disconnect();$connection=$pdo=null;
            } catch (Throwable) {
                throw new RuntimeException('Schema handoff not confirmed. Keep all consumers stopped and inspect before explicit forward resume.');
            } finally {
                try { $connection?->disconnect(); } catch (Throwable) {}
                $connection=$pdo=null;
            }
            $this->files->snapshot($plan['old_manifest']['path']); // Cold again; do not hash under a live PDO lock.
            // Known metadata only, secret/root/App-key identities and bytes are never replaced.
            foreach ($plan['metadata'] as $name=>$values) {
                $cohort->replaceKnown($name,$values['old'],$values['new'],'vacation-v7-new-'.$name);
            }
            $output=VacationWorkflowUpgradeFiles::encode($plan['channel_output']);
            if ($cohort->exists('vacation-v7-channel-admissions.json')) {
                if (!hash_equals($cohort->read('vacation-v7-channel-admissions.json'),$output)) { throw new RuntimeException; }
            } else { $cohort->reserve('vacation-v7-channel-admissions.json',$output); }
            // Manifest LAST; the still-present fence prevents admission until the receipt is complete.
            $cohort->replaceKnown('runtime-admission.json',VacationWorkflowUpgradeFiles::encode($plan['old_manifest']),
                VacationWorkflowUpgradeFiles::encode($plan['new_manifest']),'vacation-v7-new-runtime-admission.json');
            $receipt=VacationWorkflowUpgradeFiles::encode(['version'=>1,'plan'=>$plan,'completed_profile'=>'vacation-requests-v7']);
            $this->confirmCompleted($cohort,$upgrade,$plan);
            if ($cohort->exists(VacationWorkflowUpgradeFiles::RECEIPT)) {
                if (!hash_equals($cohort->read(VacationWorkflowUpgradeFiles::RECEIPT),$receipt)) { throw new RuntimeException; }
            } else { $cohort->reserve(VacationWorkflowUpgradeFiles::RECEIPT,$receipt); }
            $cohort->finish($pending,$receipt);
            return $this->result(true);
        });
    }

    private function prepare(VacationWorkflowUpgradeFiles $cohort,VacationWorkflowV7Upgrade $upgrade,
        string $directory,string $backup,array $channels):array
    {
        $old=$cohort->json('runtime-admission.json');$admission=RuntimeAdmission::fromConfiguration($old);
        if ($admission->schemaProfile!=='account-lifecycle-v5' || $admission->path!==$directory.'/application.sqlite'
            || $cohort->read('setup-closed')!=="closed\n" || !$cohort->keyExists('framework-app-key.env')) {
            throw new RuntimeException('A closed, existing canonical V5 cohort is required; finish first-user setup first.');
        }
        AccountLifecycleRuntimeCohort::requireCurrent($admission,$this->files);
        $backupEvidence=$this->files->verifyBackup($admission->path,$backup);
        if ($backupEvidence['target']['dev']!==$admission->device || $backupEvidence['target']['ino']!==$admission->inode) { throw new RuntimeException; }
        $pdo=$this->files->openReadOnly($admission->path);
        try {
            $this->requireConnectionSettings($pdo,$old,false);
            $checked=$upgrade->inspect($pdo);
            if ($checked['profile']!=='account-lifecycle-v5' || $checked['schema_version']!==$admission->schemaVersion) { throw new RuntimeException; }
            $source=$upgrade->preservedSource($pdo);
            // Every fixed additive statement is CREATE, each advances SQLite's schema cookie once.
            $future=['profile'=>'vacation-requests-v7','schema_version'=>$admission->schemaVersion+count(VacationSchema::createStatements())];
            $new=$upgrade->manifest($admission,$future);
        } finally { $pdo=null; }
        if ($this->files->verifyBackup($admission->path,$backup)!==$backupEvidence) { throw new RuntimeException; }
        $newAdmission=RuntimeAdmission::fromConfiguration($new);
        return ['version'=>1,'old_manifest'=>$old,'new_manifest'=>$new,'backup_path'=>$backup,
            'backup_evidence'=>$backupEvidence,'preserved_source'=>$source,
            'metadata'=>$this->localMetadata($cohort,$admission,$newAdmission),
            'channel_input'=>$channels,'channel_output'=>$this->channelOutput($channels,$admission,$newAdmission)];
    }

    private function validatePlan(array $plan,string $directory,string $backup,array $channels):array
    {
        if (array_keys($plan)!==['version','old_manifest','new_manifest','backup_path','backup_evidence','preserved_source','metadata','channel_input','channel_output']
            || $plan['version']!==1 || $plan['backup_path']!==$backup || $plan['channel_input']!==$channels
            || !is_array($plan['old_manifest']) || !is_array($plan['new_manifest']) || !is_array($plan['metadata'])
            || !is_array($plan['preserved_source']) || !is_array($plan['backup_evidence'])) { throw new RuntimeException('The exact private pending upgrade plan is required.'); }
        $old=RuntimeAdmission::fromConfiguration($plan['old_manifest']);$new=RuntimeAdmission::fromConfiguration($plan['new_manifest']);
        $expected=(new VacationWorkflowV7Upgrade($this->mapping))->manifest($old,[
            'profile'=>'vacation-requests-v7','schema_version'=>$old->schemaVersion+count(VacationSchema::createStatements())]);
        if ($old->path!==$directory.'/application.sqlite' || $expected!==$plan['new_manifest']
            || $plan['channel_output']!==$this->channelOutput($channels,$old,$new)
            || array_diff(array_keys($plan['metadata']),['framework-api-key.env','api-management-key.claim','ldap-settings-root-admission.json'])!==[]) {
            throw new RuntimeException('The pending upgrade lineage or channel metadata changed.');
        }
        // Recompute all recorded metadata transformations; never accept a plan-selected arbitrary overwrite.
        foreach ($plan['metadata'] as $name=>$values) {
            if (!is_array($values) || array_keys($values)!==['old','new'] || !is_string($values['old']) || !is_string($values['new'])
                || $this->rebind($name,$values['old'],$old,$new)!==$values['new']) { throw new RuntimeException; }
        }
        $this->requireBackup($plan);return $plan;
    }

    private function localMetadata(VacationWorkflowUpgradeFiles $cohort,RuntimeAdmission $old,RuntimeAdmission $new):array
    {
        $metadata=[];
        foreach ([['jwt.key','framework-api-key.env'],['encryption.key','ldap-settings-root-admission.json']] as [$key,$name]) {
            if ($cohort->keyExists($key)!==$cohort->exists($name)) { throw new RuntimeException('Partial key metadata must be inspected before upgrade.'); }
            if ($cohort->exists($name)) { $bytes=$cohort->read($name);$metadata[$name]=['old'=>$bytes,'new'=>$this->rebind($name,$bytes,$old,$new)]; }
        }
        if ($cohort->exists('api-management-key.claim')) {
            if (!isset($metadata['framework-api-key.env'])) { throw new RuntimeException; }
            $bytes=$cohort->read('api-management-key.claim');
            $metadata['api-management-key.claim']=['old'=>$bytes,'new'=>$this->rebind('api-management-key.claim',$bytes,$old,$new)];
        }
        return $metadata;
    }

    private function rebind(string $name,string $bytes,RuntimeAdmission $old,RuntimeAdmission $new):string
    {
        if ($name==='api-management-key.claim') {
            if ($bytes!=='api-management-v1:'.ExistingApiKeyAdmission::databaseProfileDigest($old)."\n") { throw new RuntimeException; }
            return 'api-management-v1:'.ExistingApiKeyAdmission::databaseProfileDigest($new)."\n";
        }
        if ($name==='framework-api-key.env') {
            if (preg_match('/\AFRAMEWORK_FRESH_API_KEY_ADMISSION=([A-Za-z0-9+\/=]+)\n\z/D',$bytes,$match)!==1) { throw new RuntimeException; }
            $json=base64_decode($match[1],true);$row=is_string($json)?json_decode($json,true,32,JSON_THROW_ON_ERROR):null;
            if (!is_array($row) || base64_encode($json)!==$match[1] || $json!==json_encode($row,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
                || $row['path']!==dirname($old->path).'/jwt.key') { throw new RuntimeException; }
            ExistingApiKeyAdmission::fromConfiguration($row,$old);
            $row['database_profile_sha256']=ExistingApiKeyAdmission::databaseProfileDigest($new);
            ExistingApiKeyAdmission::fromConfiguration($row,$new);
            return 'FRAMEWORK_FRESH_API_KEY_ADMISSION='.base64_encode(json_encode($row,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))."\n";
        }
        $row=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($row) || $bytes!==VacationWorkflowUpgradeFiles::encode($row)
            || array_keys($row)!==['version','database_profile_sha256','settings_root'] || $row['version']!==1
            || !hash_equals(LdapSyncFileSources::databaseProfileDigest($old),$row['database_profile_sha256'])
            || !is_array($row['settings_root']) || $row['settings_root']['path']!==dirname($old->path).'/encryption.key') { throw new RuntimeException; }
        // Pure pinned-file metadata validation only; settingsRoot() and actual key bytes are never read.
        LdapSyncFileSources::fromConfiguration($row['settings_root'],$row['settings_root'],$this->forbiddenRoots);
        $row['database_profile_sha256']=LdapSyncFileSources::databaseProfileDigest($new);
        return VacationWorkflowUpgradeFiles::encode($row);
    }

    private function channelOutput(array $channels,RuntimeAdmission $old,RuntimeAdmission $new):array
    {
        if (array_keys($channels)!==['api','terminal','ldap_sync']) { throw new RuntimeException('Explicit known channel metadata only.'); }
        $output=['version'=>1,'database_profile_sha256'=>ExistingApiKeyAdmission::databaseProfileDigest($new),
            'api'=>null,'terminal'=>null,'ldap_sync'=>null];
        if ($channels['api']!==null) {
            if (!is_array($channels['api'])) { throw new RuntimeException; }
            $row=$channels['api'];ExistingApiKeyAdmission::fromConfiguration($row,$old);
            $row['database_profile_sha256']=ExistingApiKeyAdmission::databaseProfileDigest($new);
            ExistingApiKeyAdmission::fromConfiguration($row,$new);$output['api']=$row;
        }
        if ($channels['terminal']!==null) {
            if (!is_array($channels['terminal']) || array_keys($channels['terminal'])!==['device','card']) { throw new RuntimeException; }
            foreach (['device','card'] as $purpose) {
                $row=$channels['terminal'][$purpose];ExistingTerminalRootAdmission::fromConfiguration($row,$old,$purpose);
                $row['database_profile_sha256']=ExistingApiKeyAdmission::databaseProfileDigest($new);
                ExistingTerminalRootAdmission::fromConfiguration($row,$new,$purpose);$output['terminal'][$purpose]=$row;
            }
        }
        if ($channels['ldap_sync']!==null) {
            FreshWebLdapSyncProfile::fromConfiguration($channels['ldap_sync'],$old,$this->forbiddenRoots);
            $row=$channels['ldap_sync'];
            if (($row['enabled']??null)!==true) { throw new RuntimeException('Only an explicitly enabled existing sync profile may be rebound.'); }
            $row['database_profile_sha256']=LdapSyncFileSources::databaseProfileDigest($new);
            FreshWebLdapSyncProfile::fromConfiguration($row,$new,$this->forbiddenRoots);$output['ldap_sync']=$row;
        }
        return $output;
    }

    private function requireMetadataValues(VacationWorkflowUpgradeFiles $cohort,array $plan):void
    {
        if ($cohort->read('setup-closed')!=="closed\n") { throw new RuntimeException; }
        $manifest=$cohort->read('runtime-admission.json');
        if (!in_array($manifest,[VacationWorkflowUpgradeFiles::encode($plan['old_manifest']),VacationWorkflowUpgradeFiles::encode($plan['new_manifest'])],true)) { throw new RuntimeException; }
        foreach ($plan['metadata'] as $name=>$values) {
            if (!in_array($cohort->read($name),[$values['old'],$values['new']],true)) { throw new RuntimeException; }
        }
        // No new optional metadata may appear halfway through an explicitly stopped upgrade.
        foreach (['framework-api-key.env','api-management-key.claim','ldap-settings-root-admission.json'] as $name) {
            if ($cohort->exists($name)!==array_key_exists($name,$plan['metadata'])) { throw new RuntimeException; }
        }
    }

    private function requireBackup(array $plan):void
    {
        if ($this->files->snapshot($plan['backup_path'])!==$plan['backup_evidence']['backup']) {
            throw new RuntimeException('The original private stopped backup changed.');
        }
        $target=$this->files->snapshot($plan['old_manifest']['path']);
        if ($target['dev']!==$plan['old_manifest']['device'] || $target['ino']!==$plan['old_manifest']['inode']) { throw new RuntimeException; }
    }

    private function confirmCompleted(VacationWorkflowUpgradeFiles $cohort,VacationWorkflowV7Upgrade $upgrade,array $plan):void
    {
        if ($cohort->read('runtime-admission.json')!==VacationWorkflowUpgradeFiles::encode($plan['new_manifest'])) { throw new RuntimeException; }
        foreach ($plan['metadata'] as $name=>$values) { if ($cohort->read($name)!==$values['new']) { throw new RuntimeException; } }
        if ($cohort->read('vacation-v7-channel-admissions.json')!==VacationWorkflowUpgradeFiles::encode($plan['channel_output'])) { throw new RuntimeException; }
        $pdo=$this->files->openReadOnly($plan['old_manifest']['path']);
        try {
            $this->requireConnectionSettings($pdo,$plan['new_manifest'],false);
            $post=$upgrade->inspect($pdo);
            if ($upgrade->manifest(RuntimeAdmission::fromConfiguration($plan['old_manifest']),$post)!==$plan['new_manifest']
                || $upgrade->preservedSource($pdo)!==$plan['preserved_source']) { throw new RuntimeException; }
        } finally { $pdo=null; }
        $this->requireBackup($plan);
    }

    private function result(bool $changed):array
    {
        return ['upgraded'=>true,'changed'=>$changed,'schema_profile'=>'vacation-requests-v7',
            'existing_rows_preserved'=>true,'keys_rotated'=>false,'server_started'=>false,
            'next_step'=>'Keep workers stopped. Reload the existing runtime manifest and the private rebound channel metadata before the ordinary configured restart.'];
    }

    /** Preserve the admitted connection policy; never normalize or silently adopt a mismatching pragma. */
    private function requireConnectionSettings(PDO $pdo,array $manifest,bool $write):void
    {
        foreach (['schema_version'=>$manifest['schema_version'],'foreign_keys'=>$manifest['foreign_keys'],
            'journal_mode'=>$manifest['journal_mode'],'synchronous'=>$manifest['synchronous'],
            'busy_timeout'=>5000,'query_only'=>$write?0:1] as $pragma=>$expected) {
            if ($pdo->query('PRAGMA '.$pragma)->fetchColumn()!==$expected) {
                throw new RuntimeException('The original admitted connection settings must remain unchanged.');
            }
        }
    }
}
