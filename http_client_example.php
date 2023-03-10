<?php

function makeGoStr(FFI $ffi, string $str): FFI\CData
{
    $goStr = $ffi->new('GoString', 0);
    $size = strlen($str);
    $cStr = FFI::new("char[$size]", 0);

    FFI::memcpy($cStr, $str, $size);
    $goStr->p = $cStr;
    $goStr->n = strlen($str);
    return $goStr;
}


function makeGoStrSlice(FFI $ffi, array $strs): FFI\CData
{
    $goSlice = $ffi->new('GoSlice', 0);
    $size = count($strs);

    $goStrs = $ffi->new("GoString[$size]", 0);
    foreach ($strs as $i => $str) {
        $goStr = makeGoStr($ffi, $str);
        $goStrs[$i] = $goStr;
    }
    $goSlice->data = $goStrs;
    $goSlice->len = $size;
    $goSlice->cap = $size;
    return $goSlice;
}

$cdefContent = file_get_contents(__DIR__ . "/libutil.h");
//remove typedef _Fcomplex and _Dcomplex
$cdefContent = preg_replace('/typedef\s+(_Fcomplex|_Dcomplex)\s+.*?;/s', '', $cdefContent);
//remove typedef float _Complex and double _Complex
$cdefContent = preg_replace('/typedef\s+(float|double)\s+_Complex\s+.*?;/s', '', $cdefContent);

//remove extern "C" {
$cdefContent = preg_replace('/extern\s+"C"\s+{/', '', $cdefContent);
//remove } (occupying the whole line)
$cdefContent = preg_replace('/^\s*}\s*$/m', '', $cdefContent);

$ffi = FFI::cdef(
    $cdefContent,
    __DIR__ . "/libutil.so"
);



// $url = makeGoStr($ffi, "http://httpbin.org/headers");
// $url2 = makeGoStr($ffi, "http://httpbin.org/get");
// $res2 = $ffi->goroutineRun($url, $url2);

// echo FFI::string($ffi->httpGet($url));
// echo FFI::string($res2[0]);
// echo FFI::string($res2[1]);

function urlMultiGet(array $urls): array
{
    global $ffi;
    $urlList = makeGoStrSlice($ffi, $urls);
    $res = $ffi->urlMultiGet($urlList);
    $resArr = [];
    for ($i = 0; $i < count($urls); $i++) {
        $resArr[] = FFI::string($res[$i]);
    }
    return $resArr;
}
$urls = [
    //test1
    "http://httpbin.org/headers", "http://httpbin.org/get"

];


//empty result1.txt result2.txt result3.txt
file_put_contents(__DIR__ . "/result1.txt", "");
file_put_contents(__DIR__ . "/result2.txt", "");
file_put_contents(__DIR__ . "/result3.txt", "");

//start timing
echo "Concurrent HTTP GET:" . PHP_EOL;
$start = microtime(true);
// $urlList = urlMultiGet($urls);

for ($i = 0; $i < 10; $i++) {
    $urlList = urlMultiGet($urls);
    file_put_contents(__DIR__ . "/result1.txt", implode(PHP_EOL, $urlList), FILE_APPEND);
}
//end timing
$end = microtime(true);
echo "time: " . ($end - $start) . "s" . PHP_EOL;


//start timing
echo "Sequential HTTP GET:" . PHP_EOL;
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    foreach ($urls as $url) {
        $urlList[] = file_get_contents($url);
    }
    file_put_contents(__DIR__ . "/result2.txt", implode(PHP_EOL, $urlList), FILE_APPEND);
}
//end timing
$end = microtime(true);
echo "time: " . ($end - $start) . "s" . PHP_EOL;


//use curl multi
echo "Curl Multi HTTP GET:" . PHP_EOL;

$start = microtime(true);

for ($i = 0; $i < 10; $i++) {
    $mh = curl_multi_init();
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $ch);
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $urlList[] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    //end timing
    file_put_contents(__DIR__ . "/result3.txt", implode(PHP_EOL, $urlList), FILE_APPEND);
}
$end = microtime(true);
echo "time: " . ($end - $start) . "s" . PHP_EOL;



// exit;

// $util = FFI::cdef(
//     "void print(char* p0);
//     int sum(int p0, int p1);",
//     __DIR__ . "/libutil.so"
// );

// $util->print(
//     (string) $util->sum(2, 4)
// );
