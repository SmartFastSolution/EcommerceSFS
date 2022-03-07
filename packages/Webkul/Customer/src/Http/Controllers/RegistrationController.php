<?php

namespace Webkul\Customer\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Webkul\Customer\Mail\RegistrationEmail;
use Webkul\Customer\Mail\VerificationEmail;
use Webkul\Shop\Mail\SubscriptionEmail;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Core\Repositories\SubscribersListRepository;
use App\Http\Controllers\SoapJdeController;
use Cookie;
use DB;

class RegistrationController extends Controller
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    /**
     * CustomerRepository object
     *
     * @var \Webkul\Customer\Repositories\CustomerRepository
     */
    protected $customerRepository;

    /**
     * CustomerGroupRepository object
     *
     * @var \Webkul\Customer\Repositories\CustomerGroupRepository
     */
    protected $customerGroupRepository;

    /**
     * SubscribersListRepository
     *
     * @var \Webkul\Core\Repositories\SubscribersListRepository
     */
    protected $subscriptionRepository;

    /**
     * Create a new Repository instance.
     *
     * @param  \Webkul\Customer\Repositories\CustomerRepository  $customer
     * @param  \Webkul\Customer\Repositories\CustomerGroupRepository  $customerGroupRepository
     * @param  \Webkul\Core\Repositories\SubscribersListRepository  $subscriptionRepository
     *
     * @return void
     */
    public function __construct(
        CustomerRepository $customerRepository,
        CustomerGroupRepository $customerGroupRepository,
        SubscribersListRepository $subscriptionRepository
    )
    {
        $this->_config = request('_config');

        $this->customerRepository = $customerRepository;

        $this->customerGroupRepository = $customerGroupRepository;

        $this->subscriptionRepository = $subscriptionRepository;
    }

    /**
     * Opens up the user's sign up form.
     *
     * @return \Illuminate\View\View
     */
    public function show()
    {
        $identification_type= DB::table('document_type')->get();
        return view($this->_config['view'],compact('identification_type'));
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $validador = new \Tavo\ValidadorEc;
        $identificacion = request()->input('identification');
        $tipo_identificacion = request()->input('id_document_type');
        $flag = "N";
        $this->validate(request(), [
            'identification'      => 'string|required|unique:customers,identification',
            'first_name' => 'string|required',
            'last_name'  => 'string|required',
            'email'      => 'email|required|unique:customers,email',
            'password'   => 'confirmed|min:6|required',
        ]);
        if($tipo_identificacion == 'C'){
            if ($validador->validarCedula($identificacion)) {
                $flag = "Y";
             } else {
                $flag='N';
                 //return 'Cédula incorrecta: '.$validador->getError();
             }
        }else if($tipo_identificacion == 'D'){
            if ($validador->validarRucSociedadPublica($identificacion)) {
                $flag = 'Y';
            } else {
                $flag='N';
                //echo 'RUC incorrecto: '.$validador->getError();
            }
        }else if($tipo_identificacion == 'N'){
            if ($validador->validarRucPersonaNatural($identificacion)) {
                $flag = 'Y';
            } else {
                $flag='N';
            }
        }else if($tipo_identificacion == 'R'){
            if ($validador->validarRucSociedadPrivada($identificacion)) {
                $flag = 'Y';
            } else {
                $flag='N';
            }
        }else{
            $flag = 'Y';
        }

        if($flag == 'Y'){
            $data = array_merge(request()->input(), [
                'password'          => bcrypt(request()->input('password')),
                'api_token'         => Str::random(80),
                'is_verified'       => core()->getConfigData('customer.settings.email.verification') ? 0 : 1,
                'customer_group_id' => $this->customerGroupRepository->findOneWhere(['code' => 'general'])->id,
                'token'             => md5(uniqid(rand(), true)),
                'subscribed_to_news_letter' => isset(request()->input()['is_subscribed']) ? 1 : 0,
                'identification'  => request()->input('identification'),
                'id_document_type'=> request()->input('id_document_type'),
            ]);





            $configs = include(base_path().'/config/configuration.php');
            $BSSV = $configs->JDE;
            if($BSSV=='ON'){
            ///////////////////////////////////////////////////////JDE CREAR CLIENTE//////////////////////////////////
            $nombre = request()->input('first_name').' '.request()->input('last_name');
            $correo =  request()->input('email');
            $tipo_identificacion = request()->input('id_document_type');
            $identificacion = request()->input('identification');
            $company = "00001";
            $JDE = (new SoapJdeController)->CrearClienteJDE($nombre,$identificacion,$company,$correo,$tipo_identificacion);
            if( $JDE == '500'){
                //abort(500, 'No se pudo registrar.');
                session()->flash('error','No pudismos registrar estre cliente');
                return redirect()->back();
            }
            ///////////////////////////////////////////////////////JDE CREAR CLIENTE//////////////////////////////////
            }

            Event::dispatch('customer.registration.before');

            $customer = $this->customerRepository->create($data);

            Event::dispatch('customer.registration.after', $customer);

            if (! $customer) {
                session()->flash('error', trans('shop::app.customer.signup-form.failed'));

                return redirect()->back();
            }

            if (isset($data['is_subscribed'])) {
                $subscription = $this->subscriptionRepository->findOneWhere(['email' => $data['email']]);

                if ($subscription) {
                    $this->subscriptionRepository->update([
                        'customer_id' => $customer->id,
                    ], $subscription->id);
                } else {
                    $this->subscriptionRepository->create([
                        'email'         => $data['email'],
                        'customer_id'   => $customer->id,
                        'channel_id'    => core()->getCurrentChannel()->id,
                        'is_subscribed' => 1,
                        'token'         => $token = uniqid(),
                    ]);

                    try {
                        Mail::queue(new SubscriptionEmail([
                            'email' => $data['email'],
                            'token' => $token,
                        ]));
                    } catch (\Exception $e) { }
                }
            }

            if (core()->getConfigData('customer.settings.email.verification')) {
                try {
                    if (core()->getConfigData('emails.general.notifications.emails.general.notifications.verification')) {
                        Mail::queue(new VerificationEmail(['email' => $data['email'], 'token' => $data['token']]));
                    }

                    session()->flash('success', trans('shop::app.customer.signup-form.success-verify'));
                } catch (\Exception $e) {
                    report($e);

                    session()->flash('info', trans('shop::app.customer.signup-form.success-verify-email-unsent'));
                }
            } else {
                try {
                    if (core()->getConfigData('emails.general.notifications.emails.general.notifications.registration')) {
                        Mail::queue(new RegistrationEmail(request()->all()));
                    }

                    session()->flash('success', trans('shop::app.customer.signup-form.success-verify'));
                } catch (\Exception $e) {
                    report($e);

                    session()->flash('info', trans('shop::app.customer.signup-form.success-verify-email-unsent'));
                }

                session()->flash('success', trans('shop::app.customer.signup-form.success'));
            }



            return redirect()->route($this->_config['redirect']);
        }else{
            session()->flash('error', trans($validador->getError()));
            return redirect()->back();
        }
    }

    /**
     * Method to verify account
     *
     * @param  string  $token
     * @return \Illuminate\Http\Response
     */
    public function verifyAccount($token)
    {
        $customer = $this->customerRepository->findOneByField('token', $token);

        if ($customer) {
            $customer->update(['is_verified' => 1, 'token' => 'NULL']);

            session()->flash('success', trans('shop::app.customer.signup-form.verified'));
        } else {
            session()->flash('warning', trans('shop::app.customer.signup-form.verify-failed'));
        }
        
        
        return redirect()->route('customer.session.index');
    }

    /**
     * @param  string  $email
     * @return \Illuminate\Http\Response
     */
    public function resendVerificationEmail($email)
    {
        $verificationData = [
            'email' => $email,
            'token' => md5(uniqid(rand(), true)),
        ];

        $customer = $this->customerRepository->findOneByField('email', $email);

        $this->customerRepository->update(['token' => $verificationData['token']], $customer->id);

        try {
            Mail::queue(new VerificationEmail($verificationData));

            if (Cookie::has('enable-resend')) {
                \Cookie::queue(\Cookie::forget('enable-resend'));
            }

            if (Cookie::has('email-for-resend')) {
                \Cookie::queue(\Cookie::forget('email-for-resend'));
            }
        } catch (\Exception $e) {
            report($e);

            session()->flash('error', trans('shop::app.customer.signup-form.verification-not-sent'));

            return redirect()->back();
        }

        session()->flash('success', trans('shop::app.customer.signup-form.verification-sent'));

        return redirect()->back();
    }
}
