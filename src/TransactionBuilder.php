<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 28/12/2017
 */

namespace NemAPI;

use Exception;

class TransactionBuilder
{
    // NEM用トランザクション操作
    public $version_ver1;
    public $version_ver2;
    public $net;
    public $recipient;
    public $amount; // 小数点アリ
    public $fee; // 小数点アリ
    public $message = '';
    public $payload;
    public $type = 257;
    private $pubkey;
    private $prikey;
    private $baseurl;
    public $mosaic;


    public function __construct($net = 'mainnet')
    {
        if ($net === 'mainnet') {
            $this->version_ver1 = 1744830465;
            $this->version_ver2 = 1744830466;
        } elseif ($net === 'testnet') {
            $this->version_ver1 = -1744830463;
            $this->version_ver2 = -1744830462;
        } else {
            throw new Exception("Error:net parameter isn't set ,net is mainnet or testnet.");
        }
        $this->net = $net;
    }

    public function setting($NEMpubkey, $NEMprikey, $baseurl = 'http://localhost:7890')
    {
        $this->pubkey = $NEMpubkey;
        $this->prikey = $NEMprikey;
        $this->baseurl = $baseurl;
    }

    private function check($amountCheck = true)
    {
        if (empty($this->version_ver1) OR empty($this->version_ver2)) {
            throw new Exception("Error:version isn't set.");
        }
        if (empty($this->recipient)) {
            throw new Exception("Error:recipient address isn't set.");
        }
        if (!isset($this->amount) AND $amountCheck) {
            throw new Exception("Error:amount isn't set.");
        }
        if (!isset($this->fee)) {
            throw new Exception("Error:fee isn't set.");
        }
    }

    public function nodelist()
    {
        if ($this->net === 'mainnet') {
            return ['62.75.171.41', '104.251.212.131', '45.124.65.125', '185.53.131.101'];
        } else {
            return ['104.128.226.60', '23.228.67.85'];
        }
    }

    public function InputMosaic($namespace, $name, $amount)
    {
        /* $mosaic内は以下のような配列
        [ makoto.metals.silver:coinを１COIN(１桁)、nem:xemを100XEM(６桁)を送る場合
            {
            "quantity": 10,
            "mosaicId": {
               "namespaceId": "makoto.metals.silver",
               "name": "coin"
            }
        },
        {
            "quantity": 100000000,
            "mosaicId": {
                "namespaceId": "nem",
                "name": "xem"
            }
        }
        ]
         */
        if (common::SerchMosaicInfo($this->baseurl, $namespace, $name)) {
            $mosaic_tmp = [
                "quantity" => $amount, // 小数点無しの生の値
                "mosaicId" => [
                    "namespaceId" => $namespace,
                    "name" => $name,
                ],
            ];
            $mosaic = $this->mosaic;
            $mosaic[] = $mosaic_tmp;
            $this->mosaic = $mosaic;
            return true;
        } else {
            // Mosaicの定義が不明
            return false;
        }
    }

    /**
     * @link https://nemproject.github.io/#provisioning-a-namespace
     * @link https://nemproject.github.io/#namespaces
     *
     * @param $namespace
     * @param bool $send
     * @return array|bool|mixed
     */
    public function provisionNamespace($namespace, $send = true)
    {
        $url = $this->baseurl . "/transaction/prepare-announce";
        $this->type = 8193;
        $POST_DATA = [
            'transaction' => [
                'timeStamp' => (time() - 1427587585), // NEMは1427587585つまり2015/3/29 0:6:25 UTCスタート
                'fee' => 150000,
                'type' => $this->type,
                'deadline' => (time() - 1427587585 + 43200), // 送金の期限
                'version' => $this->version_ver1,  // mainnetは-1744830465、testnetは-1744830463
                'signer' => $this->pubkey,  // signer　サイン主のこと
                "rentalFeeSink" => "TAMESPACEWH4MKFMBCVFERDPOOP4FK7MTDJEYP35", // for test net only
                "rentalFee" => 100 * 1000000, // 100 XEMs
                "newPart" => $namespace,
                "parent" => null,
            ],
            'privateKey' => $this->prikey,
        ];
        if ($send) {
            return common::get_POSTdata($url, json_encode($POST_DATA, JSON_PRETTY_PRINT));
        } else {
            return $POST_DATA;
        }
    }

    /**
     * @link https://nemproject.github.io/#creating-mosaics
     *
     * @param $mosaic
     * @param bool $send
     * @return array|bool|mixed
     */
    function createMosaic(
        $path,
        $name,
        $description,
        $initialSupply = 100,
        $transferable = true,
        $mutable = true,
        $divisibility = 6,
        $send = true
    ) {
        $url = $this->baseurl . "/transaction/prepare-announce";
        $this->type = 16385;
        $POST_DATA = [
            'transaction' => [
                'timeStamp' => (time() - 1427587585), // NEMは1427587585つまり2015/3/29 0:6:25 UTCスタート
                'fee' => 150000,
                'type' => $this->type,
                'deadline' => (time() - 1427587585 + 43200), // 送金の期限
                'version' => $this->version_ver1,  // mainnetは-1744830465、testnetは-1744830463
                'signer' => $this->pubkey,  // signer　サイン主のこと
                "creationFee" => 10 * 1000000, // 10 XEMs
                "creationFeeSink" => 'TBMOSAICOD4F54EE5CDMR23CCBGOAM2XSJBR5OLC',
                "mosaicDefinition" => [
                    "creator" => $this->pubkey,
                    "description" => $description,
                    "id" => [
                        "namespaceId" => $path,
                        "name" => $name,
                    ],
                    "properties" => [
                        [
                            "name" => "divisibility",
                            "value" => (string)$divisibility,
                        ],
                        [
                            "name" => "initialSupply",
                            "value" => (string)$initialSupply,
                        ],
                        [
                            "name" => "supplyMutable",
                            "value" => $mutable ? "true" : "false",
                        ],
                        [
                            "name" => "transferable",
                            "value" => $transferable ? "true" : "false",
                        ],
                    ],
                ],
            ],
            'privateKey' => $this->prikey,
        ];
        if ($send) {
            return common::get_POSTdata($url, json_encode($POST_DATA, JSON_PRETTY_PRINT));
        } else {
            return $POST_DATA;
        }
    }
    
    public function SendNEMver1($send = true)
    {
        // NEMを$addressへ送る,Non-mosaic
        // 返り値はTXID、失敗時はFalse
        $url = $this->baseurl . "/transaction/prepare-announce";
        $this->check();
        $POST_DATA =
            [
                'transaction' => [
                    'timeStamp' => (time() - 1427587585), // NEMは1427587585つまり2015/3/29 0:6:25 UTCスタート
                    'amount' => $this->amount * 1000000,      // NEMは小数点以下6桁まで有効
                    'fee' => $this->fee * 1000000,
                    'recipient' => $this->recipient,
                    'type' => $this->type,
                    'deadline' => (time() - 1427587585 + 43200), // 送金の期限
                    'message' => [
                        'payload' => isset($this->payload) ? $this->payload : bin2hex($this->message),
                        'type' => 1,
                    ],
                    'version' => $this->version_ver1,  // mainnetは-1744830465、testnetは-1744830463
                    'signer' => $this->pubkey  // signer　サイン主のこと
                ],
                'privateKey' => $this->prikey,
            ];
        // testnetは-1744830462だと以下のエラーが出る
        // expected value for property mosaics, but none was found これはNEMをモザイクとして送金する必要があるということ？
        //print_r($POST_DATA);print "<BR>"; // debug
        if ($send) {
            return common::get_POSTdata($url, json_encode($POST_DATA));
        } else {
            return $POST_DATA;
        }
        // 返り値　Array ( [innerTransactionHash] => Array ( )
        //                 [code] => 1
        //                 [type] => 1
        //                 [message] => SUCCESS
        //                 [transactionHash] => Array (
        //                                              [data] => 208a41fb815cc0dd6173213a031ba6f956ef60b6530c255a2926e9a8555198e2 )
        //                                      )
        // 返り値(error) Array ( [timeStamp] => 55043675
        //                       [error] => Not Found
        //                       [message] => invalid address 'TB235JLAOGALDATDJC7LXDMZSDMFBUMDVIBFVQ' (org.nem.core.model.Address)
        //                       [status] => 404 )
    }

    public function SendMosaicVer2($send = true)
    {
        // Mosaic送信用Ver2のトランザクション生成
        // 返り値はTXID、失敗時はFalse
        $mosaic = $this->mosaic;
        $url = $this->baseurl . "/transaction/prepare-announce";
        $this->check(false);
        $POST_DATA =
            [
                'transaction' => [
                    'timeStamp' => (time() - 1427587585),
                    'amount' => 1 * 1000000,    // 実際には１XEM取られない
                    'fee' => $this->fee * 1000000,
                    'recipient' => $this->recipient,
                    'type' => $this->type,
                    'deadline' => (time() - 1427587585 + 43200),
                    'message' => [
                        'payload' => isset($this->payload) ? $this->payload : bin2hex($this->message),
                        'type' => 1,
                    ],
                    'version' => $this->version_ver2, // Testnetは-1744830462　,mainnetは-1744830466
                    'signer' => $this->pubkey,
                    'mosaics' => $mosaic,
                ],
                'privateKey' => $this->prikey,
            ];
        if ($send) {
            return common::get_POSTdata($url, json_encode($POST_DATA));
        } else {
            return $POST_DATA;
        }
    }

    public function EstimateFee()
    {
        // 送金に必要なFeeを計算し返す
        $mosaic = $this->mosaic;
        if (is_array($mosaic)) {
            // With-mosaic
            $fee_tmp = 0;
            foreach ($mosaic as $mosaicValue) {
                $quantity = $mosaicValue['quantity'];
                $namespace = $mosaicValue['mosaicId']['namespaceId'];
                $name = $mosaicValue['mosaicId']['name'];
                $DetailMosaic = common::SerchMosaicInfo($this->baseurl, $namespace, $name);
                if (!$DetailMosaic) {
                    return false;
                }
                if ($DetailMosaic['initialSupply'] <= 10000 AND $DetailMosaic['divisibility'] === 0) {
                    // SmallBusinessMosaic
                    // 分割０でSupply１万以下のMosaicは"SmallBusinessMosaic"と呼ばれFeeが安いぞぃ
                    $fee_tmp += 1;
                } else {
                    // Others
                    // http://mijin.io/forums/forum/日本語/off-topics/717-雑談のお部屋?p=1788#post1788
                    //
                    $initialSupplybyUnit = $DetailMosaic['initialSupply'] * pow(10, $DetailMosaic['divisibility']);
                    // initialSupply は何故か小数点無しの生の値ではない（謎
                    $fee_tmp += round(max(1, min(25,
                            $quantity * 900000 / $initialSupplybyUnit) - floor(0.8 * log(9000000000000000 / $initialSupplybyUnit))));
                }
                // 徴収されるNEMやモザイクは含めなくてもよい、NISが勝手に引いてくれる
            } // end of foreach ($mosaic as $mosaicValue) {
            $fee = $fee_tmp;
        } else {
            // Non-mosaic
            $fee_tmp = floor($this->amount / 10000);
            if ($fee_tmp < 1) {
                $fee = 1;
            } elseif ($fee_tmp < 26) {
                $fee = $fee_tmp;
            } else {
                $fee = 25;
            }
        }// end of Non-mosaic

        if (isset($this->payload)) {
            if (!preg_match('/^fe([0-9abcdefABCDEF]+)/', $this->payload, $matches)) {
                throw new Exception("payload must begin with 'fe' and consist of hex code, for example 'fe01234567890abcdef' is OK.");
            }
            $fee_tmp = floor(strlen($matches[1]) / 32 / 2) + 1;
        } elseif (strlen($this->message) > 0) { // messageのFee
            $fee_tmp = floor(strlen($this->message) / 32) + 1;
        } else {
            $fee_tmp = 0;
        }
        $fee += $fee_tmp;
        $this->fee = $fee;
        return $fee;
    }

    public function EstimateLevy()
    {
        // 徴収Mosaic
        $mosaic = $this->mosaic;
        // $return はkeyにnamespace:name,valueに小数点無しの生の値
        foreach ($mosaic as $mosaicValue) {
            $quantity = $mosaicValue['quantity'];
            $namespace = $mosaicValue['mosaicId']['namespaceId'];
            $name = $mosaicValue['mosaicId']['name'];
            $MosaicData = common::SerchMosaicInfo($this->baseurl, $namespace, $name);
            if (!$MosaicData) {
                return false;
            }
            $levy = $MosaicData['detail']['mosaic']['levy'];
            if (empty($levy)) {
                continue;
            } else {
                // 徴収アリ
                if ($levy['type'] === 1) {
                    // Type1:定額徴収
                    $fee_tmp = $levy['fee'];
                } elseif ($levy['type'] === 2) {
                    // Type2:%徴収
                    // 100000 * 48 / 10000 = 480
                    // 123456 * 44 / 10000 = 543
                    $fee_tmp = floor($levy['fee'] * $quantity / 10000);
                }
                if (isset($return["{$levy['mosaicId']['namespaceId']}:{$levy['mosaicId']['name']}"])) {
                    $return["{$levy['mosaicId']['namespaceId']}:{$levy['mosaicId']['name']}"] += $fee_tmp;
                } else {
                    $return["{$levy['mosaicId']['namespaceId']}:{$levy['mosaicId']['name']}"] = $fee_tmp;
                }
                // 徴収尾張
            }
        }

        return $return;
    }

    public function ImportAddr($address)
    {
        $tmp = str_replace('-', '', $address);
        $tmp = trim($tmp);
        $this->recipient = $tmp;
    }

    public function analysis($reslt)
    {
        if (isset($reslt['message']) AND $reslt['message'] === 'SUCCESS') {
            return ['status' => true, 'txid' => $reslt['transactionHash']['data']];
        } else {
            return ['status' => false, 'message' => $reslt['message']];
        }
    }

    public function GetTX($txid)
    {
        // TXIDのみから取引情報を取得可能。
        // ただし36時間以上経過した取引情報について
        // すべてのﾉｰﾄﾞで取得できるわけではない模様。
        // NIS API Doc にはないので注意
        // 設定を変えるとﾛｰｶﾙでも使える→ nis.transactionHashRetentionTime = -1
        $nodelist = $this->nodelist();
        foreach ($nodelist as $nodelistValue) {
            $url = 'http://' . $nodelistValue . ':7890/transaction/get?hash=' . $txid;
            $data = common::get_json_array($url);
            if ($data) {
                return $data;
            } else {
                continue;
            }
        }
        return false;
    }

    public static function PubKey2Addr($PubKey, $baseurl = 'http://localhost:7890')
    {
        // 公開鍵からアドレスへ変換
        if (common::CasheGet($PubKey)) {
            $data = common::CasheGet($PubKey);
        } else {
            $url = $baseurl . '/account/get/from-public-key?publicKey=' . $PubKey;
            $data = common::get_json_array($url);
            common::CasheInsert($PubKey, $data);
        }

        if ($data) {
            return $data['account']['address'];
        } else {
            return false;
        }
    }

    public static function Addr2PubKey($address, $baseurl = 'http://localhost:7890')
    {
        // アドレスから公開鍵へ変換
        $Addr_tmp = trim(str_replace('-', '', $address));
        if (common::CasheGet($Addr_tmp)) {
            $data = common::CasheGet($Addr_tmp);
        } else {
            $url = $baseurl . '/account/get/forwarded?address=' . $Addr_tmp;
            $data = common::get_json_array($url);
            common::CasheInsert($Addr_tmp, $data);
        }

        if ($data === []) {
            return "";
        } elseif ($data === false) {
            return false;
        } else {
            return $data['account']['publicKey'];
        }
        /*
        }
        if(isset($data['account']['publicKey'])){
            return $data['account']['publicKey'];
        }else{
            return FALSE;
        }
         */
    }
}
