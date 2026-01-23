<?php

function f($n) {
    return f($n - 1) + f($n - 2);
}

echo f(5);
