<?php

namespace App\Api\V1\Controllers;

use JWTAuth;
use Validator;
use Config;
use App\User;
use App\Role;
use App\ApiSetting;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Exceptions\JWTException;
use Dingo\Api\Exception\ValidationHttpException;
use Cache;

class AuthController extends ApiController
{
    use Helpers;

    public function user()
    {
        if ($currentuser = JWTAuth::parseToken()->authenticate()) {
            $authuser = $currentuser;
        } else {
            $authuser = false;
        }
        return response()->json(compact('authuser'));
    }

    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            throw new ValidationHttpException($validator->errors()->all());
        }

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return $this->response->errorUnauthorized();
            }
        } catch (JWTException $e) {
            return $this->response->error('could_not_create_token', 500);
        }

        return response()->json(compact('token'));
    }

    public function signup(Request $request, userSetting $userSetting)
    {
        if ($userSetting->isAdmin()) {
            $signupFields = Config::get('boilerplate.signup_fields');
            $hasToReleaseToken = Config::get('boilerplate.signup_token_release');

            $userData = $request->only($signupFields);

            $validator = Validator::make($userData, Config::get('boilerplate.signup_fields_rules'));
        
            if ($validator->fails()) {
                throw new ValidationHttpException($validator->errors()->all());
            }

            User::unguard();

            $user = new User;
            $user->name = $request->name;
            $user->password = $request->password;
            $user->remember_token = '1';
            $user->email = $request->email;
            $user->save();
            User::reguard();


            if (!$request->has('supplier')) {
                $request->merge(['supplier' => 0]);
            }

            if (!$user->id) {
                return $this->response->error('could_not_create_user', 500);
            } else {
                $keys = Config::get("'user.setting_keys.".$request->role."'");

                foreach ($keys as $value) {
                    if ($request->has($value)) {
                        $apisetting = ApiSetting::create(['keys' => $value, 'val'=>$request->input($value),'user_id'=>$user->id]);
                    }
                }

                $role = Role::where('name', '=', $request->input('role'))->first();
                //$user->attachRole($request->input('role'));
                $user->roles()->attach($role->id);
            }

            if (!$apisetting->user_id) {
                return $this->response->error('could not setup setting for the user', 500);
            }

            if ($hasToReleaseToken) {
                return $this->login($request);
            }
            
            return $this->response->created();
        } else {
            return $this->respondForbidden('Forbidden from performing this action');
        }
    }

    public function recovery(Request $request)
    {
        $validator = Validator::make($request->only('email'), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            throw new ValidationHttpException($validator->errors()->all());
        }

        $response = Password::sendResetLink($request->only('email'), function (Message $message) {
            $message->subject(Config::get('boilerplate.recovery_email_subject'));
        });

        switch ($response) {
            case Password::RESET_LINK_SENT:
                return $this->response->noContent();
            case Password::INVALID_USER:
                return $this->response->errorNotFound();
        }
    }

    public function reset(Request $request)
    {
        if ($request->isMethod('post')) {
            $credentials = $request->only(
            'email',
                'password',
                'password_confirmation',
                'token'
            );

            $validator = Validator::make($credentials, [
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|confirmed|min:6',
                'password_confirmation' => 'required',
            ]);

            if ($validator->fails()) {
                throw new ValidationHttpException($validator->errors()->all());
            }
            
            $response = Password::reset($credentials, function ($user, $password) {
                $user->password = $password;
                $user->save();
            });

            switch ($response) {
                case Password::PASSWORD_RESET:
                    return $this->respondSuccess('Update Successfull');
                default:
                    return $this->response->error('could_not_reset_password', 500);
            }
        }
    }
}
