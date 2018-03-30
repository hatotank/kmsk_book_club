<?php
/**
 * @fileOverview kmsk_book_club.php
 * 君咲読書会bot 読書会の案内をTwitterに投稿
 * 
 * @author hatotank.net
 */
error_reporting(E_ALL & ~E_NOTICE);

// タイムゾーン設定
date_default_timezone_set("Asia/Tokyo");

// インクルード
require_once("twitter_api_key.php");
require_once("autoload.php");
use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * 曜日から日付差を取得
 * 
 * @param int $ws 基準曜日(曜日数値)
 * @param int $we 終了曜日(曜日数値)
 * @return int 日付差
 */
function get_day_diff($ws,$we){
  if($ws > $we){
    return abs($ws - ($we + 7));
  }else{
    return abs($ws - $we);
  }
}

// 稼働フラグOff
$kmsk_book_club = false;
$twitter = false;

// DB接続
try{
  $pdo = new PDO("sqlite:db/kmsk_book_club.db");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){
  die("Connection failed:".$e->getMessage());
}

try{
  // 現在の「曜日」と「時間」を取得
  $date = new DateTime();
  $time_week = $date->format('w'); // 0:日、1:月、2:火、3:水、4:木、5:金、6:土
  $time_hh   = $date->format('H'); // 0-24
  $time_mm   = $date->format('i'); // 0-59

  // スケジュール取得
  $sql = "select yokoku_week,yokoku_hh,yokoku_mm,open_week,open_hh,open_mm,close_week,close_hh,close_mm from eg_conf";
  $stmt = $pdo->query($sql);
  $resut_eg_conf = $stmt->fetch();

  // タスク取得
  $sql = "select run_week,run_hh,run_mm,template_file from eg_task where run_enable = 1";
  $stmt = $pdo->query($sql);
  $result = $stmt->fetchall(PDO::FETCH_ASSOC);

  // 現在時刻とスケジュール時刻判定
  foreach($result as $v){
    if($time_week == $v['run_week'] && $time_hh == $v['run_hh'] && $time_mm == $v['run_mm']){
      $kmsk_book_club = true; // 稼働フラグON
      $tpl_file = "template/".$v['template_file'];
    }
  }
}catch(PDOException $e){
  die("PDOException:".$e->getMessage());
}

// 稼働判定
if($kmsk_book_club){
  try{
    // 配列準備
    $tpl_list  = array(); // array(array['hoge']=>hoge)
    $tpl_list2 = array(); // array['hoge']=>hoge
    $tpl_media = array();

    // 開催回数取得
    $sql = "select book_count from eg_count";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    $tpl_count = $result['book_count'] + 1; // 今回分を加算

    // ストーリー強制を取得
    $sql = "select capter_id from eg_force";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    $force_capter_id = $result['capter_id'];

    // ストーリーを選択
    if($force_capter_id > 0){
      // ID指定
      $sql  = " select capter_id,capter_type,capter_title,stories,month,summary_mod,banner_file,writer_name,qtweet_url";
      $sql .= "   from eg_story";
      $sql .= "  where capter_id = $force_capter_id";
    }else{
      // 順番通り
      $sql  = " select capter_id,capter_type,capter_title,stories,month,summary_mod,banner_file,writer_name,qtweet_url";
      $sql .= "   from eg_story";
      $sql .= "  where book_enable = 1";
      $sql .= "  order by book_open asc,book_order asc";
      $sql .= "  limit 1";
    }
    $stmt = $pdo->query($sql);
    $result_story = $stmt->fetch();

    // テンプレート読込
    $tpl_content = file_get_contents($tpl_file,false,null);

    $tpl = array("【tw_yokoku】","【tw_open】","【tw_close】","【count】","【capter_title】","【capter_type】",
                 "【yokoku_start】","【yokoku_end】","【yokoku_days】","【storis】","【writer_name】","【summary_mod】",
                 "【month】","【entry】","【banner】","【capter_photo】","【qtweet】","【tag】");

    // 置き換え文字チェック
    foreach($tpl as $v){
      if(strpos($tpl_content,$v) !== false){
        $tpl_list[] = array($v=>true);
        $tpl_list2 += array($v=>true);
      }else{
        $tpl_list[] = array($v=>false);
        $tpl_list2 += array($v=>false);
      }
    }

    // 優先
    foreach($tpl_list as $v){
      $tpl_label = key($v);
      if(current($v) == false){
        continue;
      }

      switch($tpl_label){
        // 予告
        case "【tw_yokoku】":
          // 開始日時を作成(現在時刻＋開始までの差分日数)
          $st_modifire = get_day_diff($resut_eg_conf['yokoku_week'],$resut_eg_conf['open_week']) . " day";
          $date->modify($st_modifire); 
          $tpl_start = $date->format('n月j日') . $resut_eg_conf['open_hh'] . "時";

          // 終了日時を作成(開始日時＋終了までの差分日数)
          $ed_modifire = get_day_diff($resut_eg_conf['open_week'],$resut_eg_conf['close_week']) . " day";
          $date->modify($ed_modifire);
          $tpl_end = $date->format('n月j日') . $resut_eg_conf['close_hh'] . "時";

          // 開催期間を作成(当日分を+1)
          $tpl_days = (get_day_diff($resut_eg_conf['open_week'],$resut_eg_conf['close_week']) + 1). "日間";

          $tpl_search[] = $tpl_label;
          $tpl_replace[] = "";
          break;

        // 読書会
        case "【tw_open】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = "";
          break;

        // 終了
        case "【tw_close】";
          // ストーリー強制の初期化と今回の読書会開催カウントを加算。履歴もセット
          $sql = "update eg_story set book_open = book_open + 1 where capter_id = ".$result_story['capter_id'];
          $stmt = $pdo->query($sql);
          $sql = "update eg_force set capter_id = 0";
          $stmt = $pdo->query($sql);
          $sql = "update eg_count set book_count = $tpl_count";
          $stmt = $pdo->query($sql);
          $sql = "insert into eg_history values ($tpl_count,".$result_story['capter_id'].")";
          $stmt = $pdo->query($sql);

          $tpl_search[] = $tpl_label;
          $tpl_replace[] = "";
          break;
      }
    }

    // 通常
    foreach($tpl_list as $v){
      $tpl_label = key($v);
      if(current($v) == false){
        continue;
      }

      switch($tpl_label){
        case "【count】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $tpl_count;
          break;

        case "【capter_title】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $result_story['capter_title'];
          break;

        case "【capter_type】":
          $tpl_search[] = $tpl_label;
          if($result_story['capter_type'] == 1){
            $tpl_replace[] = "メイン";
          }else{
            $tpl_replace[] = "サブ";
          }
          break;

        case "【yokoku_start】":
          $tpl_search[] = $tpl_label;
          if($tpl_list2['【tw_yokoku】'] == true){
            $tpl_replace[] = $tpl_start;
          }else{
            $tpl_replace[] = "";
          }
          break;

        case "【yokoku_end】":
          $tpl_search[] = $tpl_label;
          if($tpl_list2["【tw_yokoku】"] == true){
            $tpl_replace[] = $tpl_end;
          }else{
            $tpl_replace[] = "";
          }
          break;

        case "【yokoku_days】":
          $tpl_search[] = $tpl_label;
          if($tpl_list2["【tw_yokoku】"] == true){
            $tpl_replace[] = $tpl_days;
          }else{
            $tpl_replace[] = "";
          }
          break;

        case "【storis】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $result_story['stories'];
          break;

        case "【writer_name】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $result_story['writer_name'];
          break;

        case "【summary_mod】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $result_story['summary_mod'];
          break;

        case "【month】":
          $tpl_search[] = $tpl_label;
          if($result_story['month'] == 13){
            $tpl_replace[] = "過去";  
          }elseif($result_story['month'] == 14){
            $tpl_replace[] = "未来";
          }elseif($result_story['month'] == 15){
            $tpl_replace[] = "その他";
          }else{
            $tpl_replace[] = $result_story['month']."月";
          }
          break;

        case "【entry】":
          $tpl_search[] = $tpl_label;
         
          // ストーリー登場人物を取得
          $sql  = " select count(1) as entry,(count(1)-10) as entry_sub";
          $sql .= "   from (";
          $sql .= "         select char_id";
          $sql .= "           from eg_entry";
          $sql .= "          where chapter_id = ".$result_story['capter_id'];
          $sql .= "          group by char_id";
          $sql .= "        )";

          $stmt = $pdo->query($sql);
          $result = $stmt->fetch();
          $st_entry = $result['entry'];
          $st_entry_sub = $result['entry_sub'];

          $st_entry_name = "";
          if($st_entry > 0){
            // 全員の場合は名前では無く「全員」とする
            if($st_entry == 71){
              $st_entry_name .= "全員";
            }else{
              // 登場が多い＋ID順で最大10名まで取得
              $sql  = " select a.char_id as char_id,a.entry as entry,b.char_name as char_name";
              $sql .= "   from (";
              $sql .= "         select char_id,count(1) as entry";
              $sql .= "           from eg_entry";
              $sql .= "          where chapter_id = ".$result_story['capter_id'];
              $sql .= "          group by char_id";
              $sql .= "        ) a,eg_char b";
              $sql .= "  where a.char_id = b.char_id";
              $sql .= "  order by a.entry desc,a.char_id";
              $sql .= "  limit 10";

              $stmt = $pdo->query($sql);
              $result = $stmt->fetchall(PDO::FETCH_ASSOC);
              $idx = 0;
              foreach($result as $v){
                if($idx > 0){
                  $st_entry_name .= "、";
                }
                $st_entry_name .= $v['char_name'];
                $idx++;
              }
              // 省略「他XX名」
              if($st_entry_sub > 0){
                $st_entry_name .= "、他${st_entry_sub}名";
              }
            }
            
          }
          $tpl_replace[] = $st_entry_name;
          break;

        case "【banner】":
          $tpl_search[] = $tpl_label;
          $tpl_media[] = "img/banner/".$result_story['banner_file'];
          $tpl_replace[] = "";
          break;

        case "【capter_photo】":
          $tpl_search[] = $tpl_label;
          $tpl_media[] = "img/capter-photo/".$result_story['capter_id'].".jpg";
          $tpl_replace[] = "";
          break;

        case "【qtweet】":
          $tpl_search[] = $tpl_label;
          $tpl_replace[] = $result_story['qtweet_url'];
          break;

        case "【tag】":
          $tpl_search[] = $tpl_label;
          $sql  = " select tag_name";
          $sql .= "   from eg_tag";
          $sql .= "  where chapter_id = ".$result_story['capter_id'];
          $sql .= "  order by chapter_id";

          $stmt = $pdo->query($sql);
          $result = $stmt->fetchall(PDO::FETCH_ASSOC);
          $idx = 0;
          $tag_list = "";
          foreach($result as $v){
            if($idx > 0){
              $tag_list .= "、";
            }
            $tag_list .= $v['tag_name'];
          }
          if(strlen($tag_list) > 0){
            $tpl_replace[] = $tag_list;
          }else{
            $tpl_replace[] = "なし";
          }
          break;
        }
    }

    // テンプレート置き換え
    $tpl_content = str_replace($tpl_search,$tpl_replace,$tpl_content);

    // Twitter稼働On
    $twitter = true;

  }catch(PDOException $e){
    die("PDOException:".$e->getMessage());
  }
}
$db = null;

// TwitterとLog4php処理
if($twitter){
  require_once("log4php/Logger.php");
  Logger::configure('config.xml');
  $log4 = Logger::getLogger('myLogger');

  $tw_string = $tpl_content;

  // TwitterOAuth
  $tw = new TwitterOAuth($consumer_key,$consumer_secret,$access_token,$access_token_secret);

  $tw_string = $tpl_content;
  $tw_media = array();
  $tw_media_ids = "";

  // メディア添付
  foreach($tpl_media as $v){
    $tw_media[] = $tw->upload('media/upload',['media'=>$v]);
  }
  // メディアパラメータ作成
  $idx = 0;
  foreach($tw_media as $v){
    if($idx > 0){
      $tw_media_ids = $tw_media_ids . ',' . $v->media_id_string;
    }else{
      $tw_media_ids = $v->media_id_string;
    }
    $idx++;
  }    

  // パラメータに投稿内容とメディア指定
  $parameters = ['status'=>$tw_string,'media_ids'=>$tw_media_ids,];
  // Twitter投稿
  $statues = $tw->post('statuses/update',$parameters);
  // エラーチェック
  if($tw->getLastHttpCode() == 200){
    // Tweet posted succesfully
    $log4->debug($tw_string);
    $log4->debug("Tweet posted succesfully");
  }else{
    // Handle error case
    $log4->debug($tw_string);
    $log4->debug("Handle error case");
  }
}
?>