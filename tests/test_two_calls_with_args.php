<?php

function add1($x) {
    return $x + 1;
}

function result() {
    return add1(5) + add1(3);
}

echo result();
