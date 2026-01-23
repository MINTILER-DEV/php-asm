<?php
// Complex example: User database lookup
$users = [
    101 => ["name" => "Alice", "role" => "admin", "active" => 1],
    102 => ["name" => "Bob", "role" => "user", "active" => 1],
    103 => ["name" => "Charlie", "role" => "guest", "active" => 0],
];

$userId = 102;
$user = $users[$userId];

echo "User: ";
echo $user["name"];
echo " (";
echo $user["role"];
echo ")";

if ($user["active"] == 1) {
    echo " - Active";
} else {
    echo " - Inactive";
}
?>
