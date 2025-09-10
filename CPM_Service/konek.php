<?php

// konfigurasi koneksi
$host       =  "localhost";
$dbuser     =  "postgres";
$dbpass     =  "P4ssw0rd";
$port       =  "5432";
$dbname    =  "cpm_db_dev";

// script koneksi php postgree
$link = new PDO("pgsql:dbname=$dbname;host=$host", $dbuser, $dbpass); 
 
if($link)
{
    echo "Koneksi Berhasil";
}else
{
    echo "Gagal melakukan Koneksi";
}
?>
