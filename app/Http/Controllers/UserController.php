<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\User;
use App\PhoneVerification;
use Twilio;


class UserController extends Controller
{
	public function __construct()
    {
       // $this->middleware('guest');
       $this->middleware('jwt.auth', ['except' => ['login', 'register', 'sendVerificationCode', 'verify', 'dji_login', 'dji_register', 'dji_inquiry']]);
    }  

    public function show()
    {
        $user = JWTAuth::parseToken()->authenticate();

        return response()->json(compact('user'));
    }

    private function http_digest_parse($txt)
    {
        $keys_arr = array();
        $values_arr = array();
        $cindex = 0;
        $parts = explode(',', $txt);

        foreach($parts as $p) {
            $p = trim($p);
            $kvpair = explode('=', $p);
            $kvpair[1] = str_replace("\"", "", $kvpair[1]);
            $keys_arr[$cindex] = $kvpair[0];
            $values_arr[$cindex] = $kvpair[1];
            $cindex++;
        }
      
        $ret_arr = array_combine($keys_arr, $values_arr);

        return $ret_arr;  
    }

    private function get_authorization()
    {
        $base_uri       = "https://182.253.236.154:32146";
        $request_uri    = '/auth/Login';
        $request_method = 'POST';

        $client     = new \GuzzleHttp\Client(['base_uri' => $base_uri, 'verify' => false, 'exceptions' => false]);
        $res        = $client->post($request_uri);

        $digest = $res->getHeaderLine('WWW-Authenticate');
        if (strpos($digest,'Digest') === 0) {
            $digest = substr($digest, 7);
        }

        $data       = $this->http_digest_parse($digest);
        $username   = 'dji';
        $password   = 'abcde';
        $realm      = $data['realm'];
        $qop        = $data['qop'];
        $nonce      = $data['nonce'];
        $opaque     = $data['opaque'];
        $nc         = '00000001';
        $cnonce     = '098f6bcd4621d373cade4e832627b4f6';

        $A1         = md5("$username:$realm:$password");
        $A2         = md5("$request_method:$request_uri");
        $response   = md5("$A1:$nonce:$nc:$cnonce:$qop:$A2");

        $authorization = "Digest username=\"$username\", realm=\"$realm\", nonce=\"$nonce\", uri=\"$request_uri\", qop=\"$qop\", nc=\"$nc\", cnonce=\"$cnonce\", response=\"$response\", opaque=\"$opaque\"";

        return $authorization;
    }

    public function dji_login(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'phone_number'  => 'required|max:80',
            'password'      => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $base_uri       = "https://182.253.236.154:32146";
        $request_uri    = '/auth/Login';
        $authorization  = $this->get_authorization();

        $client = new \GuzzleHttp\Client(['base_uri' => $base_uri, 'verify' => false, 'exceptions' => false]);
        $response   = $client->post($request_uri, [
            'headers'   => ['Authorization' => $authorization],
            'json' => [
               'accountID'  => $request->phone_number, 
               'hardwareID' => 'tes123',
               'password'   => md5($request->password)
            ],
        ]);

        $body   = $response->getBody()->read(1024);
        $result = json_decode((string)$body);

        return response()->json($result);
    }

    public function dji_register(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'first_name'    => 'required|regex:/^[\pL\s\-]+$/u|min:2|max:30',
            'last_name'     => 'required|regex:/^[\pL\s\-]+$/u|min:2|max:30',
            'phone_number'  => 'required|max:80',
            'email'         => 'required|email|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $base_uri       = "https://182.253.236.154:32146";
        $request_uri    = '/Services/Registrasi-Merchant';
        $authorization  = $this->get_authorization();

        $client = new \GuzzleHttp\Client(['base_uri' => $base_uri, 'verify' => false, 'exceptions' => false]);
        $response   = $client->post($request_uri, [
            'headers'   => ['Authorization' => $authorization],
            'json' => [
               'msisdn' => $request->phone_number, 
               'email'  => $request->email, 
               'name'   => $request->first_name . ' ' . $request->last_name,
               'upline' => '',
               'serial' => 'tes123'
            ],
        ]);

        $body   = $response->getBody()->read(1024);
        $result = json_decode((string)$body);

        return response()->json($result);
    }

    public function dji_inquiry()
    {
        $base_uri       = "https://182.253.236.154:32146";
        $request_uri    = '/Services/Inquiry';
        $authorization  = $this->get_authorization();

        $client = new \GuzzleHttp\Client(['base_uri' => $base_uri, 'verify' => false, 'exceptions' => false]);
        $response   = $client->post($request_uri, [
            'headers'   => ['Authorization' => $authorization],
            'json' => [
               'sessionID'      => '5D3A7B4663D87C1FF9C62FE3FA8B26C7', 
               'merchantID'     => 'DJI000315',
               'productID'      => '100302',
               'customerID'     => '39121812406',
               'accountID'      => '081932058111',
               'counterID'      => '1',
               'referenceID'    => '123456789654'
            ],
        ]);

        $body   = $response->getBody()->read(1024);
        $result = json_decode((string)$body);

        return response()->json($result);
    }

    public function register(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'first_name'    => 'required|regex:/^[\pL\s\-]+$/u|min:2|max:30',
            'last_name'     => 'required|regex:/^[\pL\s\-]+$/u|min:2|max:30',
            'phone_number'  => 'required|max:80|unique:users',
            'email'         => 'required|email|max:80|unique:users',
            'password'      => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $user = User::create([
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'phone_number'      => $request->phone_number,
            'email'             => $request->email,
            'password'          => bcrypt($request->password)
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status'    => 1,
            'message'   => 'Register successful',
            'token'     => $token
        ]);
    }

    public function login(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'phone_number'  => 'required|max:80',
            'password'      => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $credentials = $request->only('phone_number', 'password');
        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status'    => 0,
                'message'   => 'Incorrect phone number or password.'
            ]);
        }

        return response()->json([
            'status'    => 1,
            'message'   => 'Login successful',
            'token'     => $token
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'status'    => 1,
            'message'   => 'Logout successful',
        ]);
    }

    public function sendVerificationCode(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'phone_number'  => 'required|max:80|unique:users'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $phone_number      = $request->phone_number;
        $verification_code = rand(1000, 9999);

        PhoneVerification::create([
            'phone_number'      => $phone_number,
            'verification_code' => $verification_code,
        ]);

        Twilio::message($phone_number, $verification_code);

        return response()->json([
            'status'    => 1,
            'message'   => 'Send verification code successful'
        ]);
    }

    public function verify(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'phone_number'  => 'required|max:80|unique:users',
            'verification_code' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $phone_number       = $request->phone_number;
        $verification_code  = $request->verification_code;

        $phone_verification = PhoneVerification::where('phone_number', $phone_number)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($verification_code != $phone_verification->verification_code) {
            return response()->json([
                'status'    => 0,
                'message'   => 'Verify failed'
            ]);
        }

        return response()->json([
            'status'    => 1,
            'message'   => 'Verify successful'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'password'  => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 0,
                'message'   => $validator->errors()->first()
            ]);
        }

        $user = JWTAuth::parseToken()->authenticate();
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'status'    => 1,
            'message'   => 'Successfully reset password'
        ]);
    }
}
