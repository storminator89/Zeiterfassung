<?php
namespace App\Mcp;

use App\Http\Controllers\Api\LegacyApiReadController;
use App\Http\Controllers\Api\LegacyApiAttendanceController;
use Illuminate\Http\Request;

/** HTTP requests reuse the exact bearer-scoped API controllers and writer, never web identity. */
final readonly class HttpOwnApiClient implements OwnApiGateway
{
    public function __construct(private string $account, private string $profileBinding) {}
    public function binding(): string { return $this->profileBinding; }
    public function call(string $endpoint, array $query = [], ?array $body = null): array
    {
        $identity=$this->exchange('users',[],null);
        $rows=$identity['data'] ?? null;
        if (($identity['success'] ?? null)!==true || !is_array($rows) || count($rows)!==1
            || (string)($rows[0]['id'] ?? '')!==$this->account) { throw new ApiFailure('account_binding_refused'); }
        $wire=$endpoint==='users' ? $identity : $this->exchange($endpoint,$query,$body);
        return ['success'=>true,'scope'=>'own','profile_binding'=>$this->binding()]+OwnProjection::make($endpoint,$wire,$this->account,$query,$body);
    }
    private function exchange(string $endpoint,array $query,?array $body): array
    {
        $method=match($endpoint){'users'=>'identity','attendance'=>'attendance','timeentries'=>'entries','corrections'=>'corrections','work-model'=>'workModel','work-models'=>'workModelVersions',default=>throw new ApiFailure('endpoint_refused')};
        if ($body!==null && $endpoint!=='attendance') { throw new ApiFailure('endpoint_refused'); }
        $request=Request::create('/api.php/'.$endpoint.($query===[]?'':'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986)), $body===null?'GET':'POST', [], [], [], ['CONTENT_TYPE'=>'application/json'], $body===null?null:json_encode($body,JSON_THROW_ON_ERROR));
        $response=$body===null ? app(LegacyApiReadController::class)->$method($request) : app(LegacyApiAttendanceController::class)($request);
        if ($response->getStatusCode()!==200) { throw new ApiFailure('upstream_refused',$response->getStatusCode()); }
        $content=$response->getContent();
        if (!is_string($content) || strlen($content)>262144) { throw new ApiFailure('response_refused'); }
        $value=json_decode($content,true,32,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
        if (!is_array($value) || ($value['success'] ?? null)!==true) { throw new ApiFailure('response_refused'); }
        return $value;
    }
}
