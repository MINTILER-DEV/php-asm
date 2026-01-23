<?php
// Advanced example: Configuration with nested arrays and conditional logic
$config = [
    "database" => [
        "host" => "localhost",
        "port" => 5432,
        "enabled" => 1,
    ],
    "cache" => [
        "ttl" => 3600,
        "enabled" => 1,
    ],
];

$environment = "production";

if ($environment == "development") {
    echo "Running in development mode";
} elseif ($environment == "production") {
    echo "Database: ";
    echo $config["database"]["host"];
    echo ":";
    echo $config["database"]["port"];
} else {
    echo "Unknown environment";
}
?>
