<?php

namespace App\Http\Controllers\Companys;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Mail\CompanyCommonMail;
use App\Imports\UserImport;
use App\Libs\TokenPass;
use App\Http\Requests\UsersRequest;
use DB;

class UserController extends Controller
{
    /**
     * 社員一覧
     * @return [type] [description]
     */
    public function index(Request $request)
    {
      $role = 'company';
      if(!empty($request->all())) {
        $params = User::userSearch($request, Auth::id());
      } else {
        $params = User::userIndex(Auth::id());
      }
      $route = session()->get('loginType');
      // dd($route);
      // dd($role);
      return view('company.user.index', compact('params', 'route', 'role'));
    }

    /**
     * 社員新規登録
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function save(UsersRequest $request)
    {
      try {
        DB::transaction(function () use ($request) {
          $token = new TokenPass();
          $params = $request->except(['types', 'email_edit_old']);
          session()->put('company_users', 1);
          if($request->types === 'create') {
            $params['password'] = $this->random();
            $params['token'] = $token->token();
            $user = User::tableSet();
            $user->fill($params)->save();
            $status = Password::sendResetLink($request->only('email'));
            if($status != Password::RESET_LINK_SENT) {
              return $this->errHandle($e, $e->getMessage());
            }
          } else {
            $user = User::findUpdate($request);
            if($user['email'] === $request->email) {
              unset($params['email']);
            }
            // dd($params);
            $user->fill($params)->update();
          }
        });
        return redirect('/company/user');
      } catch (Exception $e) {
          return $this->errHandle($e, $e->getMessage());
      }
    }

    public function saveCsv(Request $request)
    {
      try {
        DB::transaction(function () use ($request) {
          $file = $request->file('file');
          $import = new UserImport();
          session()->put('company_users', 1);
          Excel::import($import, $file);
        });
        $complete = [
          'title' => 'CSVで登録',
          'content' => '登録が完了しました。',
        ];
        return redirect('/company/user/#completeModal')->with('complete', $complete);
      } catch (ValidationException  $e) {
        return $this->errHandle($e, $e->getMessage());
      }
    }

    /**
     * 社員稼働＆停止機能
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public function delete($id)
    {
      $user = User::findShow($id);
      if((int) $user['status'] === 1) {
        $params['status'] = 2;
      } else {
        $params['status'] = 1;
      }
      $user->fill($params)->update();
      return redirect('/company/user');
    }

    /**
     * パスワードリセット
     * @var [type]
     */
    public function renotification($id)
    {
      $user = User::findShow($id);
      $email['email'] = $user->email;
      $status = Password::sendResetLink($email);
      session()->put('company_users', 2);
      // dd($status, Password::RESET_LINK_SENT);
      if($status != Password::RESET_LINK_SENT) {
        return $this->errHandle($e, $e->getMessage());
      }
      // 管理者に送信
      $params['email'] = Auth::user()->email;
      $params['name'] = $user->name;
      Mail::send(new CompanyCommonMail($params));
      return redirect('/company/user');
    }
}
