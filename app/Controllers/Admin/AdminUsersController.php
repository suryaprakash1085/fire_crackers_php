<?php
namespace App\Controllers\Admin;

use App\Core\DB;

final class AdminUsersController extends AdminController {
    private function needSuper(): void { if ($this->admin['role'] !== 'superadmin') { flash('error', 'Only a super admin can manage users.'); redirect('/admin'); } }
    public function account(): void { $this->render('admin/account', ['title' => 'My account']); }
    public function index(): void {
        $this->needSuper();
        $this->render('admin/users', ['title' => 'Admin Users', 'rows' => DB::all('SELECT id,name,email,role,is_active,last_login_at FROM admins ORDER BY id')]);
    }
    public function save(): void {
        $this->post(); $this->needSuper();
        $name = trim((string)($_POST['name'] ?? '')); $email = strtolower(trim((string)($_POST['email'] ?? ''))); $role = (string)($_POST['role'] ?? 'staff'); $pw = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['staff', 'admin', 'superadmin'], true)) { flash('error', 'Enter a valid name, email and role.'); redirect('/admin/users'); }
        if (strlen($pw) < 10) { flash('error', 'Password must be at least 10 characters.'); redirect('/admin/users'); }
        if (DB::val('SELECT 1 FROM admins WHERE email=?', [$email])) { flash('error', 'That email already exists.'); redirect('/admin/users'); }
        DB::insert('admins', ['name' => mb_substr($name, 0, 120), 'email' => $email, 'role' => $role, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
        flash('success', 'Admin user created.'); redirect('/admin/users');
    }
    public function toggle(string $id): void {
        $this->post(); $this->needSuper();
        if ((int)$id === (int)$this->admin['id']) { flash('error', 'You cannot deactivate your own account.'); redirect('/admin/users'); }
        DB::exec('UPDATE admins SET is_active = 1 - is_active WHERE id=?', [(int)$id]); flash('success', 'User updated.'); redirect('/admin/users');
    }
    public function password(): void {
        $this->post();
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? '');
        $row = DB::one('SELECT password_hash FROM admins WHERE id=?', [$this->admin['id']]);
        if (!$row || !password_verify($cur, $row['password_hash'])) flash('error', 'Current password is incorrect.');
        elseif (strlen($new) < 10) flash('error', 'New password must be at least 10 characters.');
        else { DB::update('admins', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id=?', [$this->admin['id']]); flash('success', 'Password changed.'); }
        redirect('/admin/account');
    }
}
