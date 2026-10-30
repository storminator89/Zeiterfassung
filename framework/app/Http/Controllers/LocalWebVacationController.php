<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Vacation\VacationWeb;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\VacationCopy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Throwable;

/** Existing protected form stack/CSRF. GET never dispatches a domain command. */
final class LocalWebVacationController extends Controller
{
    public function __construct(private VacationWeb $vacation,private ChronoReads $reads,
        private Factory $auth,private Interactions $interactions) {}

    public function index(Request $request)
    {
        return $this->page($request);
    }

    public function store(Request $request)
    {
        try {
            $url=$this->url($request);
            $command=array_diff_key($request->request->all(),array_flip(['_token','csrf_token']));
            $this->vacation->apply($command);
            return new RedirectResponse($url,303);
        } catch (AuthenticationException|AuthorizationException $error) { throw $error; }
        catch (\AttendanceError|InvalidArgumentException $error) {
            return $this->page($request,$error->getMessage(),$error instanceof \AttendanceError?$error->status:400);
        } catch (Throwable) {
            return $this->page($request,'Der Vorgang ist nicht bestätigt. Gespeicherten Stand neu laden und prüfen; nicht automatisch erneut absenden.',503);
        }
    }

    private function page(Request $request,?string $error=null,int $status=200)
    {
        $language=$request->session()->get('lang','de');$language=is_string($language)&&in_array($language,['de','en','zh'],true)?$language:'de';
        try {
            [$scope,$state,$page,$preview]=$this->selection($request);
            $snapshot=$this->vacation->page($scope,$state,$page,$preview);
        } catch (AuthenticationException|AuthorizationException $failure) { throw $failure; }
        catch (InvalidArgumentException $failure) {$error=$failure->getMessage();$status=400;$snapshot=null;}
        catch (Throwable) {$error='Urlaubsanträge konnten nicht geladen werden. Kein leerer oder bestätigter Stand.';$status=503;$snapshot=null;}
        $actor=$this->auth->guard('web')->user();
        $view=view('chrono.vacation',['page'=>new AttendancePage(new Copy($language),(int)$actor->getKey(),$actor->username,$actor->role,
            $this->reads->attendanceStatus(),interactions:$this->interactions),
            'vacation'=>$snapshot,'vacationCopy'=>new VacationCopy($language),'vacationError'=>$error]);
        return $status===200?$view:response($view,$status);
    }

    private function selection(Request $request):array
    {
        parse_str((string)$request->server('QUERY_STRING',''),$query);
        $scope=$query['scope']??'own';$state=$query['state']??($scope==='review'?'pending':'all');
        $page=$query['page']??'1';$preview=$query['preview']??null;
        if (!is_string($scope)||!is_string($state)||!is_string($page)||preg_match('/\A[1-9][0-9]{0,6}\z/D',$page)!==1
            || ($preview!==null&&(!is_string($preview)||preg_match('/\A[1-9][0-9]*\z/D',$preview)!==1
                || filter_var($preview,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false))) { throw new InvalidArgumentException('Ungültige Antragsauswahl.'); }
        return [$scope,$state,(int)$page,$preview===null?null:(int)$preview];
    }

    private function url(Request $request):string
    {
        [$scope,$state,$page]=$this->selection($request);
        return 'vacation.php?'.http_build_query(['scope'=>$scope,'state'=>$state,'page'=>$page]);
    }
}
