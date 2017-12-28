<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 28/12/2017
 */

namespace NemAPI;

use Exception;


class Multisig {
    public $version_ver1;
    public $version_ver2;
    public $net;
    public $recipient;
    public $amount; // 小数点アリ
    public $fee; // 小数点アリ
    public $message = '';
    public $payload;
    public $deadline = 43200; // 60*60*24
    private $pubkey;
    private $prikey;
    private $baseurl;
    public $mosaic;

    public function __construct($pubkey,$prikey,$baseurl = 'http://localhost:7890') {
        $this->pubkey = $pubkey;
        $this->prikey = $prikey;
        $this->baseurl = $baseurl;
    }
    public function set_net($net = 'mainnet'){
        if(in_array($net, array('mainnet','testnet'),TRUE)){
            $this->net = $net;
            $this->version_ver1 = ($net === 'mainnet')? 1744830465 : -1744830463 ;
            $this->version_ver2 = ($net === 'mainnet')? 1744830466 : -1744830462 ;
        }else{
            throw new Exception('$net must be mainnet or testnet.');
        }
    }
    public function CreateAddr($OtherPubKeys,$require,$send = true){
        /* マルチシグアドレスを署名者に加えることはできないので注意
         * $OtherPubKeys に署名者のPubkeyを、$requireに必要署名数
         */
        if(isset($OtherPubKeys)){
            $all_acounts = count($OtherPubKeys);
            if($all_acounts < $require){
                throw new Exception('Required cosignatories is more than input cosignatories');
            }
            if(!isset($this->net)){
                throw new Exception('Do function net_set before function CreateAddr.');
            }
            foreach ($OtherPubKeys as $OtherPubKeysValue) {
                $modifications[] = array(
                    'modificationType' => 1,   // 1は加える、2は削除、7.5Adding and removing cosignatoriesと同じ
                    'cosignatoryAccount' => $OtherPubKeysValue
                );
                if($OtherPubKeysValue === $this->pubkey){
                    throw new Exception('Multisig account is same as a cosignator');
                    // FAILURE_MULTISIG_ACCOUNT_CANNOT_BE_COSIGNER →　署名者が既にマルチシグアドレス
                }
            }
            $url = $this->baseurl ."/transaction/prepare-announce";
            $this->fee = 16 + 6 * $all_acounts;
            $POST_DATA =
                array(
                    'transaction' => array(
                        'timeStamp' => (time() - 1427587585),
                        'fee'    => $this->fee * 1000000,
                        'type'      => 4097,
                        'deadline'  => (time() - 1427587585 + 43200),
                        'version'   => $this->version_ver2,
                        'signer'    => $this->pubkey,
                        'modifications' => $modifications,
                        'minCosignatories' => array(
                            'relativeChange' => $require
                        )
                    ),
                    'privateKey' => $this->prikey
                );
            if($send){
                // P2Pネットワークへ流す
                return common::get_POSTdata($url, json_encode($POST_DATA));
            }else{
                // P2Pネットワークへ流さずにFeeを返す
                return array('data' => $POST_DATA,'fee' => $this->fee);
            }
        }else{
            throw new Exception('Set publickeys on $OtherPubKeys as array');
        }
    }
    public function InitialTX($otherTrans,$send = true){
        /* マルチシグトランザクションの起こり
         * 最初にTXを作成した人がinnerTransactionHashのFeeを払う
         */
        $url = $this->baseurl ."/transaction/prepare-announce";
        /* これは例、トランザクションの入れ子がマルチシグ
         * innerTransactionHashとはコレのこと
        $otherTrans = array(
                'timeStamp' => (time() - 1427587585),
                'amount'    => $this->amount * 1000000,
                'fee'       => $this->fee * 1000000,
                'recipient' => $this->recipient,
                'type'      => 257,
                'deadline'  => (time() - 1427587585 + $this->deadline),
                'message'   => array(
                    'payload' => isset($this->payload)? $this->payload : bin2hex($this->message),
                    'type'    => 1
                ),
                'version'   => $this->version_ver1,
                'signer'    => $a
        );
         */
        $POST_DATA =
            array(
                'transaction' => array(
                    'timeStamp' => (time() - 1427587585),
                    'fee'       => 6 * 1000000,    // baseは6XEM、手数料はマルチシグアドレスより支払われる
                    'type'      => 4100,
                    'deadline'  => (time() - 1427587585 + $this->deadline),
                    'version'   => $this->version_ver1,
                    'signer'    => $this->pubkey,
                    'otherTrans' => $otherTrans,
                    'signatures' => array()  // ここにcosignerの署名が入る
                ),
                'privateKey' => $this->prikey
            );
        if($send){
            // P2Pネットワークへ流す
            return common::get_POSTdata($url, json_encode($POST_DATA));
        }else{
            // P2Pネットワークへ流さずにTXDATAを返す
            return array('data' => $POST_DATA,'fee' => 6);
        }
    }
    public function CosignTX($MultisigPubkey,$innerTransactionHash,$send = true){
        /* 流れてきたマルチシグトランザクションに署名
         * $innerTransactionHashは/account/unconfirmedTransactions?address=で取得できるmetaのdata
         * 署名者にはFeeはかからない
         */
        $url = $this->baseurl ."/transaction/prepare-announce";
        $POST_DATA =
            array(
                'transaction' => array(
                    'timeStamp' => (time() - 1427587585),
                    'fee'       => 6 * 1000000,    // 追加署名する人は払わなくても良い
                    'type'      => 4098,
                    'deadline'  => (time() - 1427587585 + $this->deadline),
                    'version'   => $this->version_ver1,
                    'signer'    => $this->pubkey,
                    'otherHash' => array('data' => $innerTransactionHash),
                    'otherAccount' => TransactionBuilder::PubKey2Addr($MultisigPubkey, $this->baseurl),
                ),
                'privateKey' => $this->prikey
            );
        if($send){
            // P2Pネットワークへ流す
            return common::get_POSTdata($url, json_encode($POST_DATA));
        }else{
            // P2Pネットワークへ流さずにTXDATAを返す
            return array('data' => $POST_DATA,'fee' => 0);
        }
    }
    public function ModifiMultisig($MultisigPubkey,$ADDpubkey = array(),$REMpubkey = array(),$relativeChange = 0,$send = TRUE){
        /*  Add or Remove multisig
         *  $ADDpubkeyに署名者に加えるPubkey、$REMpubkeyに署名者から除外するPubkey
         *  $relativeChangeに必要署名数を相対的に変更
         *  Feeはマルチシグアドレスより引かれる
         *  注意点、minCosignatories のみ　変更はできない（バグかも
         */
        $fee = 10 + ( count($ADDpubkey) + count($REMpubkey) ) * 6; // 10XEMをベースに１人を変更するたびに６XEM加算
        if($relativeChange !== 0){ $fee += 6; } // 必要署名数を変更で６XEM加算

        $url = $this->baseurl .'/account/get/forwarded/from-public-key?publicKey='. $MultisigPubkey ;
        $tmp = common::get_json_array($url);
        if(count($tmp['meta']['cosignatories']) === 0){
            throw new Exception($MultisigPubkey .' isn\t MultisigAddress.');
        }
        $minCosignatories = $tmp['account']['multisigInfo']['minCosignatories'];
        $otherTrans =
            array(
                'timeStamp' => (time() - 1427587585),
                'fee'       => $fee * 1000000,
                'type'      => 4097,
                'deadline'  => (time() - 1427587585 + $this->deadline),
                'version'   => (count($ADDpubkey) + count($REMpubkey) > 0)? $this->version_ver1 : $this->version_ver2 ,
                'signer'    => $MultisigPubkey
            );
        if( $relativeChange !== 0){
            $otherTrans['minCosignatories']['relativeChange'] = $relativeChange;
        }
        $otherTrans['modifications'] = array();
        if( isset($ADDpubkey) ){
            foreach ($ADDpubkey as $ADDpubkeyValue) {
                $otherTrans['modifications'][] = array(
                    'modificationType' => 1,
                    'cosignatoryAccount' => $ADDpubkeyValue
                );
            }    }
        if( isset($REMpubkey) ){
            foreach ($REMpubkey as $REMpubkeyValue) {
                $otherTrans['modifications'][] = array(
                    'modificationType' => 2,
                    'cosignatoryAccount' => $REMpubkeyValue
                );
            }    }

        $POST_DATA =
            array(
                'transaction' => array(
                    'timeStamp' => (time() - 1427587585),
                    'fee'       => 6 * 1000000,    // 追加署名する人は払わなくても良い
                    'type'      => 4100,
                    'deadline'  => (time() - 1427587585 + $this->deadline),
                    'version'   => $this->version_ver1,
                    'signer'    => $this->pubkey,
                    'otherTrans'=> $otherTrans,
                    'signatures'=> array()
                ),
                'privateKey' => $this->prikey
            );
        if($send){
            // P2Pネットワークへ流す
            $url = $this->baseurl ."/transaction/prepare-announce";
            return common::get_POSTdata($url, json_encode($POST_DATA));
        }else{
            // P2Pネットワークへ流さずにTXDATAを返す
            return array('data' => $POST_DATA,'fee' => $fee + $minCosignatories * 6);
        }
    }

    public static function checkMultisigTX($pubkey,$baseurl = 'http://localhost:7890'){
        /* 自分のアドレスにマルチシグ署名依頼が来ていないか調べる。
         * cronで定期実行するとよいと思います。Multisig::checkMultisigTX($NEMAddress, $pubkey)
         */
        $Addr = TransactionBuilder::PubKey2Addr($pubkey, $baseurl);
        $url = $baseurl .'/account/unconfirmedTransactions?address=' . $Addr;
        $data = common::get_json_array($url);


        $reslt = array();
        if(!isset($data['data'])){
            return $reslt;
        }
        foreach ($data['data'] as $dataValue) {
            $innerTransactionHash = $dataValue['meta']['data'];
            $timeStamp = $dataValue['transaction']['timeStamp'] + 1427587585;
            $type = $dataValue['transaction']['type'];
            $deadline = $dataValue['transaction']['deadline'] + 1427587585;
            $signers = count($dataValue['transaction']['signatures']);  // 既に署名した人の数
            $otherTrans = $dataValue['transaction']['otherTrans'];  // マルチシグで送金される内容

            foreach ($dataValue['transaction']['signatures'] as $SignaturesValue) {
                if($SignaturesValue['signer'] === $pubkey){
                    // 既にあなたは署名済み
                    continue;
                }
            }
            if($type === 4100 ){
                // まだ署名していないマルチシグTX
                $reslt[] = array('innerTransactionHash' => $innerTransactionHash,
                    'timeStamp'   => $timeStamp,
                    'deadline'    => $deadline,
                    'signers'     => $signers,
                    'otherTrans'  => $otherTrans);
            }else{
                continue;
            }
            return $reslt;
        }
    }

    public function analysis($reslt){
        if(isset($reslt['message']) AND $reslt['message'] === 'SUCCESS'){
            return array('status' => TRUE, 'txid' => $reslt['transactionHash']['data'],
                'inner_txid' => isset($reslt['innerTransactionHash']['data'])? $reslt['innerTransactionHash']['data'] : NULL );
        }else{
            return array('status' => FALSE, 'message' => $reslt['message'] );
        }
    }
}
