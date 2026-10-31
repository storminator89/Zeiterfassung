<?php
namespace App\Http\Middleware;

use App\Api\AdmittedLegacyApiConfiguration;
use App\Api\Management\ApiPolicyReadSnapshot;
use App\Auth\LegacyUser;
use App\Mcp\{ManagedProfile,HttpOwnApiClient,OwnApiGateway};
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/** Stateless per-request API-key identity. No session, cookie, account selector or token storage. */
final class McpHttpAccess
{
    public function __construct(private Application $app) {}
    public function handle(Request $request,Closure $next): Response
    {
        $status=$this->app->make(ManagedProfile::class)->status();
        if (!$status['available'] || !$status['enabled'] || $status['error']) { return $this->deny(404,'MCP is disabled.'); }
        if ($request->query->all()!==[] || ($request->headers->has('Origin') && $request->header('Origin')!==config('app.url'))) { return $this->deny(403,'Origin refused.'); }
        if (strlen($request->getContent())>65536) { return $this->deny(413,'Request too large.'); }
        if (!$this->app->bound(AdmittedLegacyApiConfiguration::class)) { return $this->deny(503,'API is not configured.'); }
        try {
            $api=$this->app->make(AdmittedLegacyApiConfiguration::class);$api->assertCurrent($this->app);
            if ($api->requiresOperatingPolicy() && !$this->app->make(ApiPolicyReadSnapshot::class)->read()->enabled) { return $this->deny(503,'API is disabled.'); }
        } catch (\Throwable) { return $this->deny(503,'API is unavailable.'); }
        try {
            return $this->app->make(RequireLegacyApiToken::class)->handle($request,function(Request $request) use($next): Response {
                $user=$this->app->make('auth')->guard('chrono_api')->user();
                if (!$user instanceof LegacyUser || !\App\Mcp\LocalProfile::id((string)$user->getKey())) { return $this->deny(401,'Invalid API key.'); }
                $binding=hash('sha256',json_encode([config('app.url'),config('chrono_fresh_web.instance_directory'),(string)$user->getKey(),hash('sha256',$request->bearerToken() ?? '')],JSON_THROW_ON_ERROR));
                // Request-local instance removed even after failures. No previous caller can survive.
                $this->app->instance(OwnApiGateway::class,new HttpOwnApiClient((string)$user->getKey(),$binding));
                try { return $next($request); }
                finally { $this->app->forgetInstance(OwnApiGateway::class); }
            });
        } catch (HttpResponseException $error) { return $this->deny($error->getResponse()->getStatusCode(),'Invalid or expired API key.'); }
    }
    private function deny(int $status,string $message): Response
    {
        $headers=['Content-Type'=>'application/json','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'];
        if ($status===401) { $headers['WWW-Authenticate']='Bearer realm="chrono-mcp"'; }
        return new \Illuminate\Http\Response(json_encode(['error'=>$message],JSON_THROW_ON_ERROR),$status,$headers);
    }
}
