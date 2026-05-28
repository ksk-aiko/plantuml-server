# Big picture

Phase 1: 大枠づくり（MVP）
1) docker-compose.yml を作成（サービス接続の土台）
2) nginx/default.conf を作成（/api と / のルーティング）
3) app/Dockerfile を作成（PHP API 実行環境）
4) 動作確認手順を実行（docker compose up / 疎通確認）
5) 動作確認結果を明示（確認できたこと・未確認理由）

Phase 2: 安全性・安定性の補強
1) API入力サイズ制限
2) format バリデーション
3) 空文字・null・異常系ハンドリング
4) エラー応答の安全化（情報漏えい防止）
5) Nginx 側の基本ヘッダとリクエスト制限

Phase 3: リファクタリング
1) API処理を関数分割
2) レンダリング処理の責務分離
3) 命名改善と重複除去
4) 設定値の定数化

Phase 4: 必要なら追加改善
1) ログ改善
2) ヘルスチェック
3) テスト追加
4) デプロイ補助ドキュメント追記

# Detail

## Phase 1: Core MVP

### Goal
ブラウザ上で PlantUML を入力し、リアルタイムに図を表示できる。

### Tasks
- [x] HTML/CSS/JavaScript の画面作成
- [x] Monaco Editor 導入
- [x] PHP API 作成
- [x] PlantUML Renderer 接続
- [x] SVG プレビュー実装
- [x] PNG プレビュー実装
- [x] ASCII 表示実装
- [x] debounce によるリアルタイム更新制御
- [x] エラー表示実装

### Milestone
- UML 入力から図表示まで動作する

## Phase 2: Download & Storage

### Goal
生成した図を安全にダウンロードできる。

### Tasks
- [ ] SVG ダウンロード
- [ ] PNG ダウンロード
- [ ] TXT ダウンロード
- [ ] 一時ファイル保存処理
- [ ] 一時ファイル削除処理
- [ ] ファイル名ランダム化
- [ ] 入力サイズ制限

### Milestone
- 3形式でダウンロード可能

## Phase 3: Learning Features

### Goal
チートシートと練習問題で学習できる。

### Tasks
- [ ] チートシートJSON作成
- [ ] チートシート画面作成
- [ ] Problem JSON作成
- [ ] 問題一覧ページ作成
- [ ] 問題詳細ページ作成
- [ ] Answer表示切替
- [ ] ユーザー回答と正解例の比較表示
- [ ] Pagination追加

### Milestone
- 問題を選び、回答と正解を比較できる

## Phase 4: Production Readiness

### Goal
本番環境で安全・安定して運用できる。

### Tasks
- [ ] エラーログ実装
- [ ] レート制限
- [ ] 入力バリデーション
- [ ] 一時ファイル監視
- [ ] cron cleanup設定
- [ ] セキュリティ設定
- [ ] デプロイ手順作成

### Milestone
- 本番公開可能な状態

## Phase 5: Future Expansion

### Goal
拡張可能な UML 学習・設計支援サービスへ発展させる。

### Tasks
- [ ] コンポーネント図問題追加
- [ ] デプロイメント図問題追加
- [ ] ユーザーアカウント
- [ ] 図の保存
- [ ] 履歴管理
- [ ] AI補完
- [ ] GitHub連携
- [ ] 共同編集
