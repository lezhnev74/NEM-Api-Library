<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 28/12/2017
 */

namespace NemAPI;


class common
{
    public static function getGET($name)
    {
        $ret = filter_input(INPUT_GET, $name);
        if (isset($ret)) {
            $ret = str_replace("\0", "", $ret);//Nullバイト攻撃対策
            return htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    public static function getPost($name)
    {
        $ret = filter_input(INPUT_POST, $name);
        if (isset($ret)) {
            $ret = str_replace("\0", "", $ret);//Nullバイト攻撃対策
            return htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    public static function getCookie($name)
    {
        $ret = filter_input(INPUT_COOKIE, $name);
        if (isset($ret)) {
            $ret = str_replace("\0", "", $ret);//Nullバイト攻撃対策
            return htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    public static function getRequest($name)
    {
        $ret = filter_input(INPUT_REQUEST, $name);
        if (isset($ret)) {
            $ret = str_replace("\0", "", $ret);//Nullバイト攻撃対策
            return htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    public static function FileDisass($file)
    {
        // filepassを分解、/pass/to/file.png を
        // 1=/pass/to/ ,2=file ,3=.png
        if (preg_match('/^(.*?)([^\/]+?)(\.[^.]+?)$/', $file, $matches)) {
            return $matches;
        } else {
            return false;
        }
    }


    static function get_json_array($url)
    {
        /* JSONを簡単にゲットできるモジュール
         * 返り値は配列化
         */
        //$url = "https://c-cex.com/t/dash-btc.json"; //debug
        //$json = file_get_contents($url);
        //return json_decode(mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN'),true);
        $i = 3;
        RE_TRY:
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        // ステータスコード取得
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $i--;
            sleep(2);
            if ($i > 0) {
                goto RE_TRY;
            } else {
                return false;
            }
        } else {
            $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
            $arr = json_decode($json, true);
            return $arr;
        }

    } // end of get_json_array

    static function get_POSTdata($url, $POST_DATA = null)
    {
        //$POST_DATAにPOSTデータ、key=>valueの配列型
        $i = 3;
        RE_TRY:
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        if (is_array($POST_DATA)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($POST_DATA));
        } else {
            // jsonを送信時
            curl_setopt($curl, CURLOPT_POSTFIELDS, $POST_DATA);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie');
        curl_setopt($curl, CURLOPT_COOKIEFILE, 'tmp');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        if (!is_array($POST_DATA)) {
            // jsonを送信時
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_AUTOREFERER => true,
            ];
            curl_setopt_array($curl, $options);
        }
        $json = curl_exec($curl);
        // ステータスコード取得
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($code !== 200) {
            $i--;
            sleep(2);
            if ($i > 0) {
                goto RE_TRY;
            } else {
                return false;
            }
        } else {
            $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
            $arr = json_decode($json, true);
            return $arr;
        }
    }// end of get_POSTdata

    static function RandumStr($num)
    {
        $base58 = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
        $str = '';
        for ($i = 0; $i < $num; $i++) {
            $str .= substr($base58, mt_rand(0, 57), 1);
        }
        return $str;
    }

    static function SerchMosaicInfo($baseurl, $namespace, $name)
    {
        // Mosaicの詳細を検索、検索して無かったらFalse返す
        if ($namespace !== 'nem' AND $name !== 'xem') {
            if (CasheGet($namespace)) {
                // キャッシュアリ
                $DetailMosaic = CasheGet($namespace);
            } else {
                // キャッシュ無し
                $url = $baseurl . "/namespace/mosaic/definition/page?namespace=" . $namespace;
                $DetailMosaic = get_json_array($url);
                CasheInsert($namespace, $DetailMosaic);
            }

            if (!isset($DetailMosaic['data'])) {
                echo "<BR>", $url, "<BR>", $namespace, "<BR>";
                print_r($DetailMosaic);
                return false;
            }

            foreach ($DetailMosaic['data'] as $DetailMosaicValue) {
                if ($DetailMosaicValue['mosaic']['id']['name'] === $name) {
                    foreach ($DetailMosaicValue['mosaic']['properties'] as $DetailMosaicValue2) {
                        if ($DetailMosaicValue2['name'] === 'divisibility') {
                            $divisibility = (int)$DetailMosaicValue2['value']; // ０～６
                        } elseif ($DetailMosaicValue2['name'] === 'initialSupply') {
                            $initialSupply = (int)$DetailMosaicValue2['value']; // 最小単位でないから注意
                        } elseif ($DetailMosaicValue2['name'] === 'supplyMutable') {
                            $supplyMutable = (boolean)$DetailMosaicValue2['value'];
                        } elseif ($DetailMosaicValue2['name'] === 'transferable') {
                            $transferable = (boolean)$DetailMosaicValue2['value'];
                        }
                    }
                    unset($DetailMosaicValue['mosaic']['properties']);
                    $detail = $DetailMosaicValue;
                    break;
                }
            }
            if (!isset($divisibility)) {
                return false;
            }
        } elseif ($namespace === 'nem' AND $name === 'xem') {
            $divisibility = 6;  // 小数点以下６桁まで可能
            $initialSupply = 8999999999;
            $supplyMutable = false;  // trueだと追加発行可能
            $transferable = true;  // trueだと譲渡可能
            $detail = [
                'meta' => ['id' => 1],
                'mosaic' => [
                    'creator' => '',
                    'description' => 'Its dummy data',
                    'id' => [
                        'namespaceId' => '',
                        'name' => '',
                    ],
                    'levy' => [],
                ],
            ];
        } else {
            return false;
        }
        return [
            'divisibility' => $divisibility,
            'initialSupply' => $initialSupply,
            'supplyMutable' => $supplyMutable,
            'transferable' => $transferable,
            'detail' => $detail,
        ];
        /*返り値
    {
      "divisibility": 2,
      "initialSupply": 10000,
      "supplyMutable": true,
      "transferable": true,
      "detail": {
        "meta": {
          "id": 191
        },
        "mosaic": {
          "creator": "47900452f5843f391e6485c5172b0e1332bf0032b04c4abe23822754214caec3",
          "description": "もってるといいことがある....はず、FaucetのDonationのお返しに送金されるよ",
          "id": {
            "namespaceId": "namuyan",
            "name": "namu"
          },
          "levy": {}
        }
      }
    }
         */
    }

    /*  NEM API 用キャッシュ
     *  Addr⇔PubKey、Mosaic定義に使用
     */
    static function CasheInsert($key, $value)
    {
        global $nem_api_library_cache;
        $nem_api_library_cache[$key] = $value;
    }

    static function CasheGet($key)
    {
        global $nem_api_library_cache;
        if (isset($nem_api_library_cache[$key])) {
            return $nem_api_library_cache[$key];
        } else {
            return false;
        }
    }

}
