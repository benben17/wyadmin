<?php

namespace App\Api\Controllers\Auth;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Api\Services\Sys\UserServices;
use App\Api\Controllers\BaseController;
use App\Models\Company as CompanyModel;


/**
 * @OA\Tag(
 *     name="auth认证",
 *     description="用户登录、退出、修改密码、用户信息等"
 * )
 */
class AuthController extends BaseController
{
    public function __construct()
    {
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"auth认证"},
     *     summary="登录",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name", "password"},
     *       @OA\Property(
     *          property="username",
     *          type="string",
     *          description="用户名"
     *       ),
     *       @OA\Property(
     *          property="password",
     *          type="string",
     *          description="密码"
     *       )
     *     ),
     *       example={
     *              "username": "test", "password": "123456"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function login(Request $request)
    {
        $credentials['name'] = $request->input('username');
        $credentials['password'] = $request->input('password');
        if (!$token = auth('api')->attempt($credentials)) {
            return $this->error('用户名或密码错误!');
        }
        $user = auth('api')->user();
        $result = CompanyModel::with("product")->find($user->company_id);
        $service = new UserServices;
        $project_info = $service->getLoginUser($user->id);
        $nickname = "";
        if ($user->unionid) {
            $wxUser = \App\Api\Models\Weixin\WxUser::where('unionid', $user['unionid'])->first();
            $isBind = 1;
            $nickname = $wxUser->nickname;
        } else {
            $isBind = 0;
        }
        $data = [
            'token'    => $token,
            'uuid'     => $user->id,
            'username' => $user->name,
            'is_admin' => $user->is_admin,
            'phone'    => $user->phone,
            'realname' => $user->realname,
            'is_bind'  => $isBind,
            'role_id'  => $user->role_id
        ];
        if ($user->depart_id == 0) {
            $departName = '系统管理员';
        } else {
            $depart = getDepartById($user->depart_id);
            $departName = $depart->name ?? '';
        }

        $data['project_info'] = $project_info;
        $data['info'] = [
            'name' => $user->realname,
            'uid' => $user->id,
            'avatar' => $user->avatar,
            'avatar_full' => getOssUrl($user->avatar),
            'access' => ['admin'],
            'company_name' => $result->name,
            'company_access' => [$result->product->en_name],
            'nickname' => $nickname,
            'depart_name' => $departName,
            'days' => getVariable($user['company_id'], 'year_days')
        ];

        // 获取用户系统权限，当用户is admin 的时候返回空
        $data['menu_list'] = $service->userMenu($user);

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/editpwd",
     *     tags={"auth认证"},
     *     summary="修改密码",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"oldpassword", "password"},
     *       @OA\Property(
     *          property="oldpassword",
     *          type="string",
     *          description="原密码"
     *       ),
     *       @OA\Property(
     *          property="password",
     *          type="string",
     *          description="新密码"
     *       )
     *     ),
     *       example={
     *              "oldpassword": "123456", "password": "123456"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function updatepwd(Request $request)
    {
        $validatedData = $request->validate([
            'oldpassword'   => 'required|max:10|min:6',
            'password'      => 'required|max:10|min:6'
        ]);
        $oldpassword = $request->input('oldpassword');
        $password = Hash::make($request->input('password'));
        $this->authUser(); // 用户认证
        $uid = auth('api')->user()->id;
        $user = \App\Models\User::find($uid);
        if (!Hash::check($oldpassword, $user->password)) {
            return $this->error('旧密码不正确!');
        }
        $user->password = $password;
        $result = $user->save();
        if (!$result) {
            return $this->error("密码修改失败！");
        }
        return $this->success("", "密码修改成功！");
    }

    /**
     * @OA\Post(
     *     path="/api/auth/userinfo",
     *     tags={"auth认证"},
     *     summary="获取当前用户信息",
     *     @OA\Parameter(
     *         name="Authorization",
     *         description="Bearer {token}",
     *         required=false,
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="{code: int32, message:string, data:[]}"
     *     )
     * )
     */
    public function userinfo(Request $request)
    {
        try {
            $this->authUser();
            $uid = auth('api')->user()->id;
            $result = \App\Models\User::with('company')->with('role:id,name')->find($uid);
            if (!$result) {
                return $this->error('用户信息查询失败!');
            }
            $result->avatar_full = getOssUrl($result->avatar);
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/userinfo/edit",
     *     tags={"auth认证"},
     *     summary="个人用户信息编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"email"},
     *       @OA\Property(
     *          property="email",
     *          type="String",
     *          description="邮箱"
     *       )
     *     ),
     *       example={"email": "", "phone": "", "remark": "", "avatar": ""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function editUserInfo(Request $request)
    {
        try {
            $this->authUser();
            $uid = auth('api')->user()->id;
            $DA = $request->toArray();
            $userInfo = \App\Models\User::find($uid);
            $userInfo->u_uid  = $uid;
            $userInfo->email  = isset($DA['email']) ? $DA['email'] : "";
            $userInfo->phone  = isset($DA['phone']) ? $DA['phone'] : "";
            $userInfo->remark = isset($DA['remark']) ? $DA['remark'] : "";
            $userInfo->avatar = isset($DA['avatar']) ? $DA['avatar'] : "";
            $result = $userInfo->save();
            if (!$result) {
                return $this->error('用户信息更新失败!');
            }
            return $this->success('用户信息更新成功.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/userinfo/bind_wx",
     *     tags={"auth认证"},
     *     summary="用户绑定微信",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(
     *          property="email",
     *          type="String",
     *          description="邮箱"
     *       )
     *     ),
     *       example={
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function bindWeChat(Request $request)
    {
        $this->authUser();
        $DA = $request->toArray();
        $uid = auth('api')->user()->id;
        $userInfo = \App\Models\User::find($uid);
        $userInfo->wx_openid = $DA['openid'];
        $result = $userInfo->save();
        if (!$result) {
            return $this->error('绑定失败!');
        }
        return $this->success('绑定成功.');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"auth认证"},
     *     summary="退出登录",
     *     @OA\Parameter(
     *         name="Authorization",
     *         description="Bearer {token}",
     *         required=false,
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="{code: int32, message:string, data:[]}"
     *     )
     * )
     */
    public function logout()
    {
        auth('api')->logout();
        return $this->success('已成功退出登录！');
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
