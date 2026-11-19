<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            Response::redirect('index.php?action=dashboard');
        }
        $this->viewPlain('auth/login', ['csrf' => $this->csrfField()]);
    }

    public function login(): void
    {
        $this->checkCsrf();
        $email = trim((string)$this->input('email', ''));
        $pass  = (string)$this->input('mot_de_passe', '');

        if ($email === '' || $pass === '') {
            $this->flash('error', 'Veuillez renseigner votre email et votre mot de passe.');
            Response::redirect('index.php?action=login');
        }

        if (Auth::attempt($email, $pass)) {
            if ($this->input('se_souvenir')) {
                Auth::remember((int)Auth::id());
            }
            Response::redirect('index.php?action=dashboard');
        }

        $this->flash('error', 'Identifiants invalides ou compte désactivé.');
        Response::redirect('index.php?action=login');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('index.php?action=login');
    }
}
