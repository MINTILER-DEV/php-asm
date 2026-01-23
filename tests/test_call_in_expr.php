<?php

function get_value() {
    return 5;
}

function add_and_call() {
    return get_value() + 3;
}

echo add_and_call();
