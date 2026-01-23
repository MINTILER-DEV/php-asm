<?php
// Nested arrays
$users = [
    1 => ["name" => "Alice", "age" => 25],
    2 => ["name" => "Bob", "age" => 30],
    3 => ["name" => "Charlie", "age" => 22],
];

echo "User 2: ";
echo $users[2]["name"];
echo ", Age: ";
echo $users[2]["age"];
?>
