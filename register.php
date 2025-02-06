<?php

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $course = $_POST['course'];
    $yearlevel = $_POST['yearlevel'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (idno, firstname, middlename, lastname, course, yearlevel, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssssss', $idno, $firstname, $middlename, $lastname, $course, $yearlevel, $username, $password);

    if ($stmt->execute()) {
        $message = "Registered successfully!";
    } else {
        $message = "Failed to register!";
    }

    $stmt->close();
    $conn->close();
    echo $message;
}
?>