<?php
$servername = "localhost";   // o la IP de tu servidor
$username   = "root";        // tu usuario de MariaDB
$password   = "root1234";            // tu contraseña de MariaDB
$dbname     = "formato_compatibilidad";   // la base de datos que creamos

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>