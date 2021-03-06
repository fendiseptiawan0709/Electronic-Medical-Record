<?php

namespace App\Http\Controllers;

use Auth;
use App\User;
use App\Lab;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Mail\Account_Verification;
use PHPMailer;

//use Illuminate\Support\Facades\Storage;

class Lab_Controller extends Controller {

    function auth() {
        if (Auth::guest()) {
            return redirect('login');
        } elseif (Auth::user()->status == 'Admin') {
            return redirect('admin');
        } elseif (Auth::user()->status == 'Doctor') {
            return redirect('doctor');
        } elseif (Auth::user()->status == 'Lab') {
            //Access granted
        } elseif (Auth::user()->status == 'Pharmacy') {
            return redirect('pharmacy');
        } elseif (Auth::user()->status == 'Patient') {
            return redirect('patient');
        }
    }

    //Untuk menampilkan dashboard lab
    public function home() {
        $lab_id = DB::table('lab')->where('user_id', Auth::user()->id)->value('id');
        $data['today_checkup_count'] = DB::table('lab_checkup')->where('lab_id', $lab_id)->where('date', date('Y-m-d'))->count();
        $data['next_checkup_count'] = DB::table('lab_checkup')->where('lab_id', $lab_id)->where('date', (new DateTime('+1 day'))->format('Y-m-d'))->count();
        $data['completed_checkup_count'] = DB::table('lab_checkup')->where('lab_id', $lab_id)->where('date', '<', date('Y-m-d'))->count();

        return view('lab.home', $data);
    }

    //Untuk memanajemen data lab
    public function manage() {
        if (Auth::guest() == TRUE || Auth::user()->status !== 'Admin') {
            return $this->auth();
        }

        $lab = DB::table('users')->join('lab', 'users.id', '=', 'lab.user_id')->where('users.is_enabled', '=', '1')->get();

        $data = array(
            'lab' => $lab
        );

        return view('lab.manage', $data);
    }

    //Untuk menampilkan form tambah lab
    public function lab_add() {
        if (Auth::guest() == TRUE || Auth::user()->status !== 'Admin') {
            return $this->auth();
        }

        return view('lab.add');
    }

    //Menyimpan data lab ke database
    public function lab_add_submit(Request $request) {
        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'name' => 'required',
            'city' => 'required',
            'address' => 'required',
            'mobile' => 'numeric',
            'telephone' => 'numeric'
                ], [
            'email.unique' => 'Email pernah digunakan',
            'name.required' => 'Nama harap diisi',
            'city.required' => 'Kota (domisili) harap diisi',
            'address.required' => 'Kolom alamat harap diisi',
            'mobile.required' => 'Kolom nomor ponsel diisi dengan angka',
            'telephone.required' => 'Kolom telepon diisi dengan angka'
        ]);

        $password = $this->generate_pass();

        $account = new User;

        $account->email = $request->input('email');
        $account->password = password_hash($password, PASSWORD_DEFAULT);
        $account->name = $request->input('name');
        $account->city = $request->input('city');
        $account->address = $request->input('address');
        $account->mobile = $request->input('mobile');
        $account->telephone = $request->input('telephone');
        $account->status = 'Lab';
        $account->remember_token = md5(date('Y-m-d H:i:s'));

        $account->save();

        $account_id = DB::table('users')->where('email', $request->input('email'))->value('id');
        $request->file('photo')->storeAs(('/public/assets/userfile/lab/' . $account_id . '/profile'), md5($request->input('email')) . '.' . $request->file('photo')->extension());

        $lab = new Lab(
                array(
            'name' => $request->input('name'),
            'mobile' => $request->input('mobile'),
            'telephone' => $request->input('telephone'),
            'city' => $request->input('city'),
            'address' => $request->input('address'),
            'photo' => 'storage/assets/userfile/lab/' . $account_id . '/profile/' . md5($request->input('email')) . '.' . $request->file('photo')->extension()
                )
        );

        $account_lab = $account::where('email', $request->input('email'))->first();
        $account_lab->lab()->save($lab);

        $this->sendmail($request->input('name'), $request->input('email'), $password);

        return Redirect::back()->with('message', 'Data laboratorium ' . $request->input('name') . ' telah berhasil ditambahkan.');
    }

    public function generate_pass($length = 15) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function sendmail($name, $email, $password) {
        $mail = new PHPMailer;

        //Enable SMTP debugging.
        $mail->SMTPDebug = 3;
        //Set PHPMailer to use SMTP.
        $mail->isSMTP();
        //Set SMTP host name
        $mail->Host = "smtp.gmail.com";
        //Set this to true if SMTP host requires authentication to send email
        $mail->SMTPAuth = true;
        //Provide username and password
        $mail->Username = "fendi.septiawan0709@gmail.com";
        $mail->Password = "ciqxlyaimkwzoizi";
        //If SMTP requires TLS encryption then set it
        $mail->SMTPSecure = "tls";
        //Set TCP port to connect to
        $mail->Port = 587;

        $mail->From = "administrator@emr.com";
        $mail->FromName = "Electronic Medical Record - Aktivasi Akun";

        $mail->addAddress($email, $name);

        $mail->isHTML(true);

        $mail->Subject = "Subject Text";
        $mail->Body = "<div style='font-size: 14px'><p>Hai, " . $name . ".</p><p>Selamat datang di Electronic Medical Record (EMR).<p>Untuk dapat menggunakan akun Anda, silakan login dengan <br/>Email : " . $email . ".<br/>Password : " . $password . ".</p><p>Harap ganti password Anda segera setelah login.</p><p>Terima kasih.</p>";
        $mail->AltBody = "Hai, " . $name . ". Selamat datang di Electronic Medical Record (EMR). Untuk dapat menggunakan akun Anda, silakan login dengan email : " . $email . ". password : " . $password . ". Harap ganti password Anda segera setelah login. Terima kasih.";

        if (!$mail->send()) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        } else {
            echo "Message has been sent successfully";
        }
    }

    public function sendmail_mailgun($name, $email, $pass) {
        $mail_param = new Account_Verification($name, $email, $pass);
        Mail::to($email)->send($mail_param);
    }

    //Untuk menampilkan profile laboratorium
    public function profile($id = NULL) {
        if (Auth::guest() == TRUE || ( Auth::user()->status !== 'Lab' && Auth::user()->status !== 'Admin')) {
            return $this->auth();
        }

        if ($id == NULL) {
            $lab = DB::table('lab')->where('user_id', '=', Auth::user()->id)->get();
            $account = DB::table('users')->where('id', '=', Auth::user()->id)->where('is_enabled', '1')->get();
        } else {
            $lab = DB::table('lab')->where('id', '=', $id)->get();
            $lab_id = DB::table('lab')->where('id', '=', $id)->value('user_id');
            $account = DB::table('users')->where('id', '=', $lab_id)->where('is_enabled', '1')->get();
        }

        $data = array(
            'lab' => $lab,
            'account' => $account
        );

        return view('lab.profile', $data);
    }

    //Untuk menampilkan form edit profil lab
    public function profile_edit($id = NULL) {
        if (Auth::guest() == TRUE || (Auth::user()->status !== 'Lab' && Auth::user()->status !== 'Admin')) {
            return $this->auth();
        }

        if ($id == NULL) {
            $lab = DB::table('lab')->where('user_id', '=', Auth::user()->id)->get();
            $account = DB::table('users')->where('id', '=', Auth::user()->id)->get();
        } else {
            $lab = DB::table('lab')->where('id', '=', $id)->get();
            $lab_id = DB::table('lab')->where('id', '=', $id)->value('user_id');
            $account = DB::table('users')->where('id', '=', $lab_id)->where('is_enabled', '1')->get();
        }

        $data = array(
            'lab' => $lab,
            'account' => $account
        );

        return view('lab.edit-profile', $data);
    }

    //Untuk menyimpan data perubahan profil lab
    public function profile_edit_submit(Request $request) {
        $lab = new Lab;

        $email = $request->input('email');

        $user_id = DB::table('users')->where('email', $email)->value('id');

        $lab->where('user_id', $user_id)->update(
                array(
                    'name' => $request->input('name'),
                    'city' => $request->input('city'),
                    'address' => $request->input('address'),
                    'mobile' => $request->input('mobile'),
                    'telephone' => $request->input('telephone')
                )
        );

        $user = new User;

        $user->where('id', $user_id)->update(
                array(
                    'name' => $request->input('name'),
                    'city' => $request->input('city'),
                    'address' => $request->input('address'),
                    'mobile' => $request->input('mobile'),
                    'telephone' => $request->input('telephone')
                )
        );

        if (Auth::user()->status == 'Lab') {
            return redirect('lab/profile')->with('message', 'Profil Anda telah berhasil diperbarui.');
        }

        return redirect('admin/lab/manage')->with('message', 'Data laboratorium ' . $request->input('name') . ' telah berhasil diperbarui.');
    }

    //Untuk mengedit password lab
    public function password_edit() {
        if (Auth::guest() == TRUE || Auth::user()->status !== 'Lab') {
            return $this->auth();
        }

        return view('lab.edit-password');
    }

    //Untuk memproses form edit password pasien - Hanya untuk Pasien
    public function password_edit_submit(Request $req) {
        $email = $req->input('email');
        $cur_password = $req->input('password');
        $get_old_pass = DB::table('users')->where('email', $email)->value('password');
        $check = password_verify($cur_password, $get_old_pass);
        if ($check == TRUE) {
            $new_password = $req->input('password_new');
            $ver_new_password = $req->input('password_new_verify');
            if ($new_password == $ver_new_password) {
                $admin = new User;
                $admin->where('email', $email)->update(array('password' => password_hash($new_password, PASSWORD_DEFAULT)));
                return redirect('lab/profile')->with('message', 'Password Anda telah diperbarui.');
            } else {
                return Redirect::back()->with('message', 'Password Anda tidak sesuai.');
            }
        } else {
            return Redirect::back()->with('message', 'Password Anda tidak sesuai.');
        }

        return redirect('lab/profile')->with('message', 'Password Anda berhasil diperbarui.');
    }

    //Untuk menghapus data lab
    public function delete($id) {
        if (Auth::guest() == TRUE || Auth::user()->status !== 'Admin') {
            return $this->auth();
        }

        $user_id = DB::table('lab')->where('id', $id)->value('user_id');
        $lab_name = DB::table('lab')->where('id', $id)->value('name');
        DB::table('users')->where('id', $user_id)->update(array('is_enabled' => '0'));

        return redirect('admin/lab/manage')->with('message', 'Data laboratorium ' . $lab_name . ' telah berhasil dihapus.');
    }

}
