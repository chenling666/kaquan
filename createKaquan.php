<?php
include_once('KaquanTools.php');

$kaquan = new KaquanTools();
$prefix = '1';
$nums = 10;

//创建批量兑换码
$data = $kaquan->makeCard($prefix, $nums);
var_dump($data);

//批量兑换码入库
//连接数据库
$conn = mysqli_connect('localhost', 'root', 'root@123', 'juhe');
$sql = "INSERT INTO kaquan(`no`, `password`) VALUES ";
$str = '';
for ($i = 0; $i < count($data); $i++) {
    $str .= "('" . $data[$i]['no'] . "', '" . $data[$i]['password'] . "'),";
}
//var_dump($str);
if ($str) {
    $str = rtrim($str, ',');
    //var_dump($str);
    $sql .= $str;
    $result = mysqli_query($conn, $sql);
}
mysqli_close($conn);