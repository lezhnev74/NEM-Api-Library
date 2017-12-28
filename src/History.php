<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 28/12/2017
 */

namespace NemAPI;

use Exception;


class History
{
    // 履歴を取得
    public $version_ver1;
    public $version_ver2;
    public $net;
    public $pubkey;
    public $prikey;
    public $baseurl;
    public $pageid = null;


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

    public function setting($NEMpubkey, $NEMprikey = null, $baseurl = 'http://localhost:7890')
    {
        $this->pubkey = $NEMpubkey;
        $this->address = TransactionBuilder::PubKey2Addr($NEMpubkey, $baseurl);
        $this->prikey = $NEMprikey;
        $this->baseurl = $baseurl;
    }

    private function checkWDM()
    {
        if (!isset($this->prikey)) {
            throw new Exception('Error:$prikey is not set.');
        }
        // ローカルでのみ使用可能
        //if(preg_match('/[localhost|127\.0\.0\.1]/',$this->baseurl) !== 1){
        //    throw new Exception('Error:It function work only on localhost.');
        //}
    }

    public function IncomingWDM()
    {
        /* Transaction data with decoded messages
         * 暗号化されたmessageを復号化して
         */
        $this->checkWDM();
        $url = $this->baseurl . '/local/account/transfers/incoming';
        $POST_DATA = array('value' => $this->prikey);
        if (isset($this->pageid)) {
            $POST_DATA['id'] = $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_POSTdata($url, json_encode($POST_DATA));
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    public function OutgoingWDM()
    {
        /* Transaction data with decoded messages
         * 暗号化されたmessageを復号化して
         */
        $this->checkWDM();
        $url = $this->baseurl . '/local/account/transfers/outgoing';
        $POST_DATA = array('value' => $this->prikey);
        if (isset($this->pageid)) {
            $POST_DATA['id'] = $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_POSTdata($url, json_encode($POST_DATA));
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    public function AllWDM()
    {
        /* Transaction data with decoded messages
         * 暗号化されたmessageを復号化して
         */
        $this->checkWDM();
        $url = $this->baseurl . '/local/account/transfers/all';
        $POST_DATA = array('value' => $this->prikey);
        if (isset($this->pageid)) {
            $POST_DATA['id'] = $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_POSTdata($url, json_encode($POST_DATA));
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    private function check()
    {
        if (!isset($this->address)) {
            throw new Exception('Error:$address is not set.');
        }
    }

    public function Incoming()
    {
        /* 通常のHistory取得
         */
        $this->check();
        $url = $this->baseurl . '/account/transfers/incoming?address=' . $this->address;
        if (isset($this->pageid)) {
            $url .= '&id=' . $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_json_array($url);
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    public function Outgoing()
    {
        /* 通常のHistory取得
         */
        $this->check();
        $url = $this->baseurl . '/account/transfers/outgoing?address=' . $this->address;
        if (isset($this->pageid)) {
            $url .= '&id=' . $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_json_array($url);
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    public function All()
    {
        /* 通常のHistory取得
         */
        $this->check();
        $url = $this->baseurl . '/account/transfers/all?address=' . $this->address;
        if (isset($this->pageid)) {
            $url .= '&id=' . $this->pageid;
        } // 2ページよりidによりページを指示
        $history = common::get_json_array($url);
        if (!empty($history['data'])) {
            $this->pageid = $history['data'][count($history['data']) - 1]['meta']['id'];
            return $history['data'];
        } else {
            return false;
        }
    }

    public function DecodeArray($transaction)
    {
        if (!isset($transaction[0])) {
            throw new Exception('Error:$transaction key is not numeristic.');
        }
        $reslt = array();
        foreach ($transaction as $transactionValue) {
            $tmp = array();
            $tmp['height'] = $transactionValue['meta']['height'];
            $tmp['timeStamp'] = $transactionValue['transaction']['timeStamp'] + 1427587585;
            // 内部ハッシュが実際の送金内容
            if (!empty($transactionValue['meta']['innerHash'])) {
                // Multisig
                $tmp['hash'] = $transactionValue['meta']['innerHash']['data'];
                $tmp['Multisig'] = true;
                $tx_tmp = $transactionValue['transaction']['otherTrans'];
            } else {
                // No Multisig
                $tmp['hash'] = $transactionValue['meta']['hash']['data'];
                $tmp['Multisig'] = false;
                $tx_tmp = $transactionValue['transaction'];
            }
            $tmp['siger'] = $tx_tmp['signer'];
            $tmp['fee'] = $tx_tmp['fee'];


            if ($tx_tmp['type'] === 257 AND $tx_tmp['version'] === $this->version_ver1) {
                // XEM送金トランサクション
                $tmp['txtype'] = 1;
                $tmp['amount'] = $tx_tmp['amount'];
                $tmp['recipient'] = $tx_tmp['recipient'];
                $tmp['message'] = $tx_tmp['message'];

            } elseif ($tx_tmp['type'] === 257 AND $tx_tmp['version'] === $this->version_ver2) {
                // Mosaic送金トランザクション
                $tmp['txtype'] = 2;
                $tmp['recipient'] = $tx_tmp['recipient'];
                $tmp['mosaic'] = $tx_tmp['mosaics'];
                $tmp['message'] = $tx_tmp['message'];
            } elseif ($tx_tmp['type'] === 4097) {
                // Multisig変換または編集トランザクション
                $tmp['txtype'] = 3;
                $tmp['minCosignatories'] = $tx_tmp['minCosignatories'];
                $tmp['modifications'] = $tx_tmp['modifications'];
            }
            $reslt[] = $tmp;
        }
        return $reslt;
    }
}

