<?php

function f1($n) {
    return 5;
}

function f2($n) {
    return 3;
}

function combined($n) {
    return f1($n - 1) + f2($n - 2);
}

echo combined(5);
