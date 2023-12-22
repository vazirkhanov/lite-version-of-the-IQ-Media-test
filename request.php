<?php
// Класс, отвечающий за управление подключением к базе данных PDO
class Connection {
// Параметры подключения к базе данных
    private $server = "mysql:host=127.0.0.1:3308;dbname=ваше имя дб";
    private $username = "ваш логин админа бд";
    private $password = "ваш пароль от бд";
    private $options  = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,);
    protected $conn;
// Метод для открытия соединения с базой данных
    public function open() {
        try {
            $this->conn = new PDO($this->server, $this->username, $this->password, $this->options);
            return $this->conn;
        } catch (PDOException $e) {
            echo "There is some problem in connection: " . $e->getMessage();
        }
    }
// Метод для закрытия соединения с базой данных
    public function close() {
        $this->conn = null;
    }
}

// Класс, отвечающий за функциональность укорачивания ссылок
class LinkShortener {
// Объект подключения к базе данных - private $conn
    private $conn;
// Конструктор для инициализации с подключением к базе данных
    public function __construct($conn) {
        $this->conn = $conn;
    }
// Метод генерации случайного токена для сокращенной ссылки
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
// Метод укорачивания заданной ссылки
    public function shortenLink($link) {
        $token = '';
// Продолжаем генерировать токены, пока не будет найден уникальный
        while (true) {
            $token = $this->generateToken();
            $stmt = $this->conn->prepare("SELECT * FROM `links` WHERE `token` = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                break;
            }
        }
 // Заливаем ссылку и ее токен в базу данных
        $stmt = $this->conn->prepare("INSERT INTO `links` (`link`, `token`) VALUES (:link, :token)");
        $stmt->bindParam(':link', $link);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
// Возвращаем сокращенную ссылку в случае успеха, в противном случае возвращаем false
        if ($stmt->rowCount() > 0) {
            return $_SERVER['SERVER_NAME'] . '/' . $token;
        } else {
            return false;
        }
    }
// Метод для перенаправления на оригинальную ссылку с использованием токена
    public function redirectToOriginalLink($token) {
        $stmt = $this->conn->prepare("SELECT * FROM `links` WHERE `token` = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
 // Если токен существует, перенаправляем на исходную ссылку; в противном случае выводим сообщение об ошибке
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

// Выполняем укорачивание или перенаправление ссылок в зависимости от наличия 'cut_link' в параметрах запроса
if (isset($_GET['cut_link'])) {
    $request = trim($_GET['cut_link']);
    $request = htmlspecialchars($request);
    $shortenedLink = $linkShortener->shortenLink($request);
// Выводим сообщение об успехе или неудаче в зависимости от результата операции укорачивания
    if ($shortenedLink) {
        $_GET['cut_link'] = $shortenedLink;
        // echo "Ссылка добавлена в систему!";
    } else {
        // echo "Ссылка не добавлена";
    }
} else {
// Если 'cut_link' отсутствует, попытайтесь перенаправить на исходную ссылку, используя токен в URL    
    $URI = $_SERVER['REQUEST_URI'];
    $token = substr($URI, 1);

    if (strlen($token)) {
        $linkShortener->redirectToOriginalLink($token);
    }
}

// Закрытие соединения после использования
$connection->close();
?>
