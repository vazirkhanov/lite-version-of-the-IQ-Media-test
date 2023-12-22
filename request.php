<?php
//Клас для подключения к бд по PDO
class Connection {
    private $server = "mysql:host=127.0.0.1:3308;dbname=ваше имя дб";
    private $username = "ваш логин админа бд";
    private $password = "ваш пароль от бд";
    private $options  = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,);
    protected $conn;

    public function open() {
        try {
            $this->conn = new PDO($this->server, $this->username, $this->password, $this->options);
            return $this->conn;
        } catch (PDOException $e) {
            echo "There is some problem in connection: " . $e->getMessage();
        }
    }

    public function close() {
        $this->conn = null;
    }
}


class LinkShortener {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function generateToken($min = 5, $max = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDFEGHIJKLMNOPRSTUVWXYZ0123456789';
        $new_chars = str_split($chars);

        $token = '';
        $rand_end = mt_rand($min, $max);

        for ($i = 0; $i < $rand_end; $i++) {
            $token .= $new_chars[mt_rand(0, sizeof($new_chars) - 1)];
        }

        return $token;
    }

    public function shortenLink($link) {
        $token = '';

        while (true) {
            $token = $this->generateToken();
            $stmt = $this->conn->prepare("SELECT * FROM `links` WHERE `token` = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                break;
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO `links` (`link`, `token`) VALUES (:link, :token)");
        $stmt->bindParam(':link', $link);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $_SERVER['SERVER_NAME'] . '/' . $token;
        } else {
            return false;
        }
    }

    public function redirectToOriginalLink($token) {
        $stmt = $this->conn->prepare("SELECT * FROM `links` WHERE `token` = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            header("Location: " . $row['link']);
            exit();
        } else {
            die("Ошибка токена");
        }
    }
}

// Создание объекта Connection
$connection = new Connection();
$conn = $connection->open();

// Создание объекта LinkShortener с передачей подключения
$linkShortener = new LinkShortener($conn);


if (isset($_GET['cut_link'])) {
    $request = trim($_GET['cut_link']);
    $request = htmlspecialchars($request);
    $shortenedLink = $linkShortener->shortenLink($request);

    if ($shortenedLink) {
        $_GET['cut_link'] = $shortenedLink;
        // echo "Ссылка добавлена в систему!";
    } else {
        // echo "Ссылка не добавлена";
    }
} else {
    $URI = $_SERVER['REQUEST_URI'];
    $token = substr($URI, 1);

    if (strlen($token)) {
        $linkShortener->redirectToOriginalLink($token);
    }
}

// Закрытие соединения после использования
$connection->close();
?>
