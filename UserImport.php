<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Mail;
use App\Mail\CreateUserMail;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use App\Libs\TokenPass;
use App\Libs\TabelSet;
use Validator;
use Config;

class UserImport implements  ToCollection, WithStartRow
{
    // csvの1行目にヘッダがあるので2行目から取り込む
    // public static $startRow = 2;
    // protected $startRow = Config::get('csvStart.start');

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $token = new TokenPass();
        $table = new TabelSet();
        $new = $table->tabelSessionSet(Config::get('table.table_user'));
        // dd($new);
        $count = 1;
        $error_list = [];
        // dd($collection->toArray());
        foreach ($collection->toArray() as $key => $val) {
          $validator = Validator::make($val, [
            '0' => "required|max:255|unique:".$new.",employee_id",
            '1' => "required|max:255|string",
            '2' => "required|max:255|unique:".$new.",email",
            '3' => "required|max:255|string",
            '4' => "required|max:255|string",
            '5' => "required|max:255|string",
          ]);
          if ($validator->fails()) {
            $error_list[$count] = $validator->errors()->all();
          }
          $count++;
        }
        if(count($error_list) > 0) {
          return back()->withErrors($error_list)->withInput();
        }
        // $validator = Validator::make($collection->toArray(), [
        //   '*.0' => "required|max:255|unique:".$new.",employee_id",
        //   '*.1' => "required|max:255|string",
        //   '*.2' => "required|max:255|unique:".$new.",email",
        //   '*.3' => "required|max:255|string",
        //   '*.4' => "required|max:255|string",
        //   '*.5' => "required|max:255|string",
        // ]);
        // if ($validator->fails()) {
        //   dd($validator->errors()->all());
        //   return back()->withErrors($validator)->withInput();
        // }
        // dd($validator);

        foreach ($collection as $key => $val) {
          $user = User::tableSet();
          if($val[4] === '男') {
            $sex = 1;
          } elseif($val[4] === '女') {
            $sex = 2;
          } else {
            $sex = 3;
          }
          $params = [
            'employee_id' => $val[0],
            'name' => $val[1],
            'email' => $val[2],
            'birth' => $val[3],
            'sex' => $sex,
            'hire_date' => $val[5],
            'password' => $token->random(),
            'token' => $token->token(),
          ];
          $user->fill($params)->save();
          $para['email'] = $val[2];
          $status = Password::sendResetLink($para);
          sleep(1);
          if($status != Password::RESET_LINK_SENT) {
            return $this->errHandle($e, $e->getMessage());
          }
          // $email = 'xyzqrz60+'.$val[0].'@gmail.com';
          // logger($email);
          // Mail::send(new CreateUserMail($params));
        }
    }

    /**
     * 初期ヘッダの行数設定
     * @return int [description]
     */
    public function startRow(): int
    {
      // dd(Config::get('csvStart.start'));
      return Config::get('csvStart.start');
      // return self::$startRow;
    }
}
