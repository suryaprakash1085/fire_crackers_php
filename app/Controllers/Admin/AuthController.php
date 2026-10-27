<?php
namespace App\Controllers\Admin;

use App\Core\{Auth, Controller, View};

final class AuthController extends Controller {
    public function login(): void {
        if (Auth::user()) redirect('/admin');
        $error = null;
        if (is_post()) {
            if (!csrf_valid()) $error = 'Session expired. Please try again.';
            else {
                $r = Auth::attempt(strtolower(trim((string)($_POST['email'] ?? ''))), (string)($_POST['password'] ?? ''));
                if ($r === true) redirect('/admin');
                $error = $r;
            }
        }
        View::render('admin/login', ['error' => $error, 'title' => 'Admin Login']);
    }
    public function logout(): void { $this->requireCsrf(); Auth::logout(); redirect('/admin/login'); }
}
