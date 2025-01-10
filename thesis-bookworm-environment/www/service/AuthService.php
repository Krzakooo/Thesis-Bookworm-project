<?php

namespace Bookworm\service;

require_once '../model/User.php';

use PDO;
use Bookworm\model\User;

class AuthService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser(): ?User
    {
        if ($this->isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            return $this->getUserById($userId);
        } else {
            return null;
        }
    }

    public function login(string $email, string $password): bool
    {

        $user = $this->getUserByEmail($email);

        if ($user && password_verify($password, $user->getPassword())) {

            return true;
        }

        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
    }

    public function signUp(string $email, string $password, string $username): ?User
    {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("INSERT INTO users (email, password, username) VALUES (:email, :password, :username)");
        $stmt->execute(['email' => $email, 'password' => $hashedPassword, 'username' => $username]);

        if ($stmt->rowCount() > 0) {
            $userId = $this->pdo->lastInsertId();

            $profilePicture = 'default.jpg';
            return new User($userId, $email, $hashedPassword, $username, $profilePicture);
        }

        return null;
    }

    public function getUserById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ? new User($user['id'], $user['email'], $user['password'], $user['username'], $user['profile_picture']) : null;
    }

    public function getUserByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        return $user ? new User($user['id'], $user['email'], $user['password'], $user['username'], $user['profile_picture']) : null;
    }

    public function getUserByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ? new User($user['id'], $user['email'], $user['password'], $user['username'], $user['profile_picture']) : null;
    }

    public function updateUserDetails($userId, $email, $username)
    {
        $sql = "SELECT email FROM users WHERE id = :userId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($currentUser && $currentUser['email'] !== $email) {
            return "You can't update email address!";
        }

        $sql = "UPDATE users SET email = :email, username = :username WHERE id = :userId";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            'email' => $email,
            'username' => $username,
            'userId' => $userId,
        ]);

        return $success ? true : "Failed to update user details";
    }

    public function updateProfilePicture(int $userId, string $profile_picture): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :id");
        $profile_picture = 'uploads/' . $profile_picture;
        $success = $stmt->execute(['profile_picture' => $profile_picture, 'id' => $userId]);

        return $success;
    }


}
