# kmsk_book_club
君咲読書会botの実装

## 概要
君咲読書会botの中身となります。  
定期実行(cron)にて曜日、時間、分が一致した場合、Twitterに読書会の案内を投稿します。  
twitter_api_key.phpとconfig.xmlの中身は必ず書き換えてください。  

## イメージ
- [稼働ツイッター](https://twitter.com/kmsk_book_club)

## 仕様
- 投稿文章は簡易テンプレート機能とタスク機能により容易に追加変更できます。

  ---
  【tw_yokoku】【予告】第【count】回読書会のお知らせ

  今回の作品：  
  【capter_title】

  日時：  
  【yokoku_start】～【yokoku_end】の【yokoku_days】

  作家：  
  【writer_name】

  話数：  
  全【storis】話

  ルール：  
  『 \#君咲読書会 』のハッシュタグを付けて感想をつぶやいたり、読みながら実況するだけ！

  \#あんガル \#君咲読書会【capter_photo】

  ---

- テンプレート内の置換文字は以下の通り(※要データ登録)  
  - 特殊 
    - 【tw_yokoku】…内部で予告の処理を行う(【yokoku_start】【yokoku_end】【yokoku_days】が使用可能)  
    - 【tw_open】  …内部で開催の処理を行う  
    - 【tw_close】 …内部で終了の処理を行う(DB更新)  
  - 通常
    - 【count】       …開催回数
    - 【capter_title】…チャプター名
    - 【yokoku_start】…開始日時　※【tw_yokoku】時のみ有効
    - 【yokoku_end】  …終了日時　※【tw_yokoku】時のみ有効
    - 【yokoku_days】 …開催期間　※【tw_yokoku】時のみ有効
    - 【storis】      …チャプターストーリー話数
    - 【writer_name】 …ライター名
    - 【summary_mod】 …あらすじなど
    - 【month】       …ストーリー時系列(月)
    - 【banner】      …指定の画像ファイルを使用
    - 【capter_photo】…あんガルメモリーズのチャプター画像を使用
    - 【qtweet】      …公式Twitter引用ツイート(
    - 【entry】       …ストーリー登場人物を10名まで出力(超えた場合は他○名省略)
    - 【tag】         …関連タグ
 

## フォルダ・ファイル構成
```
├ autoload.php … TwitterOAuth
├ config.xml … log4phpメール送信定義
├ kmsk_book_club.php … 本体
├ LICENSE … ライセンス
├ README.md … 現在のファイル
├ twitter_api_key.php … APIキー定義
│
├ db … DBファイル格納
│ ├ create_db.sh … kmsk_book_club.dbを作成(sqlite3)
│ └ kmsk_book_club.sql … テーブルダンプ
│
├ img … 画像格納
│ ├ banner … バナー画像
│ └ capter-photo … チャプター画像
│
├ log4php … log4php格納
│
├ template … テンプレート格納(タスク登録分追加)
│ ├ close.txt … 終了
│ ├ hosoku.txt … 補足
│ ├ open.txt … 開催
│ ├ senden.txt … 宣伝
│ ├ temp.txt … 一時
│ └ yokoku.txt … 予告
│
└ twitteroauth … TwitterOAuth格納
```

## データベース仕様
**eg_char**

|列名|型|内容|
|:---|:---|:---|
|char_id|integer|(PK)生徒ID|
|char_name|text|名前|
|char_first_name|text|名|
|char_last_name|text|姓|

**eg_conf**

|列名|型|内容|
|:---|:---|:---|
|yokoku_week|integer|予告曜日(0:日 1:月 2:火 3:水 4:木 5:金 6:土)|
|yokoku_hh|integer|予告時(0-24)|
|yokoku_mm|integer|予告分(0-59)|
|open_week|integer|開催曜日|
|open_hh|integer|開催時|
|open_mm|integer|開催分|
|close_week|integer|終了曜日|
|close_hh|integer|終了時|
|close_mm|integer|終了分|

**eg_count**

|列名|型|内容|
|:---|:---|:---|
|book_count|integer|読書会開催回数|

**eg_entry**

|列名|型|内容|
|:---|:---|:---|
|story_id|integer|(PK)ストーリーID|
|char_id|integer|(PK)生徒ID|
|char_order|integer|並び順|
|chapter_id|integer|チャプターID|

**eg_force**
|列名|型|内容|
|:---|:---|:---|
|capter_id|integer|強制実行チャプターID|

**eg_history**
|列名|型|内容|
|:---|:---|:---|
|book_count|integer|開催回数|
|chapter_id|integer|チャプターID|

**eg_story**

|列名|型|内容|
|:---|:---|:---|
|capter_id|integer|(PK)チャプターID|
|capter_type|integer|チャプタータイプ(1:メイン 2:サブ)|
|capter_title|text|チャプタータイトル|
|stories|integer|チャプター話数|
|month|integer|時系列(月1~12 13:過去 14:未来 15:その他)|
|release_order|integer|リリース順(チャプタータイプ毎)|
|summary_original|text|オリジナルのあらすじ(公式お知らせ/ツイッター)|
|summary_mod|text|あらすじ(不要情報削除)|
|qtweet_url|text|引用ツイートURL|
|banner_file|text|バナーファイル名(ファイル拡張子まで)|
|renewal|integer|リニューアル区分(0:旧 1:新)|
|writer_name|text|ライター名(複数の場合は話数が多い順)|
|book_enable|integer|読書会紹介(0:Off 1:On)|
|book_open|integer|読書会紹介済(0:未 N>0:済)|
|book_order|integer|読書会順|

**eg_tag**

|列名|型|内容|
|:---|:---|:---|
|tag_name|text|関連タグ|
|chapter_id|integer|チャプターID|

**eg_task**

|列名|型|内容|
|:---|:---|:---|
|run_week|integer|(PK)実行曜日|
|run_hh|integer|(PK)実行時|
|run_mm|integer|(PK)実行分|
|run_enable|integer|実行(0:Off 1:On)|
|template_file|text|使用テンプレートファイル|
|biko|text|備考・メモ|

## その他
画像はありません。  
ガル勢の糧になれば幸いです。  

## 作者
[hatotank](https://github.com/hatotank)

## ライセンス
MIT

以下のパッケージを使用しています。
- [TwitterOAuth](https://twitteroauth.com/)
- [log4php](https://logging.apache.org/log4php/)