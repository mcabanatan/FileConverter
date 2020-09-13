<?php
require_once ('FileConverter.php');
$fileConvert = new FileConverter('customers.csv');
$fileConvert->convertFile();