<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 28/12/2017
 */

namespace NemAPI;

use Exception;

class Apostille {
    // アポスティーユ作成
    public $payload ;
    public $algo ;
    public $type ;
    public $net;
    public $filename ;
    public $recipient;
    public $txid;


    public function __construct($filename = NULL) {
        if(isset($filename)){
            $this->setting($filename);
        }
    }
    public function set_net($net){
        if(!in_array($net, array('mainnet','testnet'),true)){
            throw new Exception ("Error:parameter of net ,mainnet or testnet are allowd.");
        }
        $type = $this->type;
        $this->net = $net;
        if($net === 'mainnet'){
            //$this->version = -1744830465;
            $this->recipient = ($type === 'public')?'NCZSJHLTIMESERVBVKOW6US64YDZG2PFGQCSV23J':'NAX4LLSZ7N3JHWQYQSAMGABTD5SVHFEJD2BTWQBN';
        }else{
            //$this->version = -1744830463;
            $this->recipient = ($type === 'public')?'TC7MCY5AGJQXZQ4BN3BOPNXUVIGDJCOHBPGUM2GE':'TDXJZ42QNFCGEZVCZFZSE2QPKQU7MDZ4SNO6NOI4';
        }
    }

    public function setting($filename,$type = 'public',$algo = 'sha256',$net = 'mainnet'){
        if(!preg_match('/^(.+?)(\.[^.]+?)$/', $filename)){
            throw new Exception("Error:$filename ファイルに拡張子を加えて下さい。");
        }
        $this->filename = $filename;
        $this->type = $type;
        $this->algo = $algo;
        if(!file_exists($filename)){
            throw new Exception ("Error:$filename isn't exist.");
        }
        if(!in_array($type, array('public','private'),true)){
            throw new Exception ("Error:parameter of type ,public or private are allowd.");
        }
        if(!in_array($algo, array('md5','sha1','sha256','sha3-256','sha3-512'),true)){
            throw new Exception ("Error:parameter of algo ,md5 ,sha1 ,sha256 or keccak(SHA3) are allowd.");
        }
        $this->set_net($net);
    }
    public function Run(){
        /* https://github.com/strawbrary/php-sha3
         * SHA3のハッシュはこのモジュールを導入
         * 下部にSHA3のハッシュ化classを置いてあるがかなり遅い
         * 参考メモ
         * PATHを通していない場合→/opt/lampp/bin/phpize
         * ./configure --enable-sha3 --with-php-config=/opt/lampp/bin/php-config
         * cp /modules/sha3.so /opt/lampp/lib/php/extensions/no-debug-non-zts-20121212/
         */
        $hex = 'fe'; // 形式 HEX
        switch ($this->algo) {
            case 'md5' : $algo = 1; break;
            case 'sha1' : $algo = 2; break;
            case 'sha256' : $algo = 3; break;
            case 'sha3-256' : $algo = 8; break;
            case 'sha3-512' : $algo = 9; break;
            default:throw new Exception ("Error:未対応の暗号方式です。");
        }
        if($algo < 4){
            $hash = hash_file($this->algo, $this->filename);
        }elseif($algo === 8){
            $all = file_get_contents($this->filename);
            //$hash = sha3($all,256);
            set_time_limit(250);
            $hash = Sha3_0xbb::hash($all, 256 );
            set_time_limit(30);
        }elseif($algo === 9){
            $all = file_get_contents($this->filename);
            //$hash = sha3($all,512);
            set_time_limit(250);
            $hash = Sha3_0xbb::hash($all, 512 );
            set_time_limit(30);
        }else{
            die("Error:$algo が例外です。");
        }
        if($this->type === 'public'){
            $this->payload = $hex .'4e54590'. $algo . $hash;
        }else{
            // 暗号化の仕方がわからない
            throw new Exception ("Error:暗号化に未対応.");
            $this->payload = $hex .'4e54598'. $algo . $hash;
        }
    }
    public function send($NEMpubkey,$NEMprikey,$baseurl = 'http://localhost:7890') {
        $nem = new TransactionBuilder($this->net);
        $nem -> setting($NEMpubkey, $NEMprikey, $baseurl);
        $nem->payload = $this->payload;
        $nem -> amount = 0;
        $nem -> recipient = $this->recipient;
        $nem ->EstimateFee();
        $tmp = $nem ->SendNEMver1();
        $reslt = $nem->analysis($tmp);
        $reslt['fee'] = $nem->fee;
        if($reslt['status']){
            $this->txid = $reslt['txid'];
        }
        return $reslt;
    }
    public function Outfile($dir = '/opt/lampp/htdocs/apo/'){
        // あらかじめ保存場所$dirを設定
        if(!isset($this->txid)){
            throw new Exception("There isn't TXID. send may be not success.");
        }
        $txid = $this->txid;
        $date = date("Y-m-d");
        preg_match('/.*?([^\/]+?)(\.[^.]+?)$/', $this->filename, $matches);
        $dest = $dir . $matches[1] ." -- Apostille TX $txid -- Date $date" .$matches[2];
        return copy($this->filename, $dest);
    }

    public function Check($baseurl = 'http://localhost:7890'){
        $filename_original = $this->filename;
        $pattern = '/^(.*?)([^\/]+?)\s\-\-\sApostille\sTX\s([0-9abcdefABCDEF]+?)\s\-\-\sDate\s([0-9\-]+?)(\.[^.]+?)$/';
        if(!preg_match($pattern, $filename_original, $matches)){
            throw new Exception("Error:FilenameがApostilleで使われる形式ではありません。");
        }
        $dirpass = $matches[1];
        $filename = $matches[2];
        $txid = $matches[3];
        $date = $matches[4];
        $ex = $matches[5];

        $nem = new TransactionBuilder($this->net);
        $txdata = $nem->GetTX($txid);
        if(!$txdata){
            // 登録されていないか全ノード死亡
            return array('status' => FALSE ,'code' => 1);
        }
        if(preg_match('/^fe4e5459([08])([0-9abcdef])([0-9abcdef]*)/', $txdata['transaction']['message']['payload'], $matches)){
            $type = $matches[1];
            $hash = $matches[3];
            switch ($matches[2]) {
                case 1:$this->algo = 'md5';break;
                case 2:$this->algo = 'sha1';break;
                case 3:$this->algo = 'sha256';break;
                case 8:$this->algo = 'sha3-256';break;
                case 9:$this->algo = 'sha3-512';break;
                default:return array('status' => FALSE ,'code' => 3);
            }
        }else{
            // messageが正規でない
            return array('status' => FALSE ,'code' => 2);
        }
        $this->Run();
        if($this->payload === $txdata['transaction']['message']['payload']){
            return array('status' => true ,'code' => 0,'detail' => $txdata['transaction']);
        }
    }
    public function analysis($reslt){
        if(isset($reslt['message']) AND $reslt['message'] === 'SUCCESS'){
            return array('status' => TRUE, 'txid' => $reslt['transactionHash']['data']);
        }else{
            return array('status' => FALSE, 'message' => $reslt['message'] );
        }
    }
}

