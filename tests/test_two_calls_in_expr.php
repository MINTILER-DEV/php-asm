<?php

function f1() {
    return 5;
}

function f2() {
    return 3;
}

function combined() {
    return f1() + f2();
}

echo combined();
