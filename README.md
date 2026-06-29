# PlantUMLServer

## 概要
PlantUMLServer は、ブラウザ上で PlantUML を編集し、SVG・PNG・TXT 形式で結果を確認・保存・学習できる Web アプリケーションです。Docker Compose で起動できるため、ローカル環境でも再現しやすく、実運用を意識した API 安全化・監視・クリーンアップまで含めて学べます。

## 特徴
- ブラウザ上での PlantUML 編集とリアルタイムプレビュー
- SVG・PNG・TXT のマルチフォーマット対応
- 一時ファイル保存と削除 API によるダウンロード運用
- 入力バリデーション、レート制限、エラーログなどの運用向け安全対策
- 一時ファイル監視 API と cron cleanup による保守性向上
- 学習用チートシートと問題演習（比較表示・ページング対応）

## このプロジェクトを通して学べること・習得できること

### 1. Web アーキテクチャ設計の基礎から実践
- 単一画面アプリ + API + レンダラ（PlantUML）という分離構成
- リバースプロキシ（NGINX）を前段に置いた責務分離
- UI 層、API 層、外部サービス層を分ける設計思想
- 小規模でも運用を想定した設計判断（ログ、監視、制限、クリーンアップ）

### 2. API 設計・入力検証・異常系ハンドリング
- JSON API の基本設計（エンドポイント、入力、出力、エラーコード）
- 必須項目チェック、型チェック、空文字チェック、サイズ制限
- 400 / 404 / 413 / 429 / 502 などの HTTP ステータス設計
- 安全なエラー応答（内部情報を漏らさない）

### 3. セキュリティ対策の実践
- NGINX のヘッダ設定（nosniff、frame deny、referrer policy など）
- リクエストボディサイズ制限による DoS 耐性強化
- 危険ファイル拡張子・隠しファイルへのアクセス遮断
- API へのレート制限導入とログ連携

### 4. 運用・SRE 観点の基礎
- 構造化エラーログの設計と活用
- 一時ファイル監視 API による可観測性の確保
- cron による定期クリーンアップ
- ヘルスチェック、起動直後のウォームアップ考慮、障害切り分け手順

### 5. Docker / コンテナ運用の習得
- Docker Compose による複数サービス連携
- イメージビルドとデプロイの反復手順
- コンテナ内プロセス管理（PHP サーバー + cron）
- ログ確認・設定反映確認などのデバッグフロー

### 6. 学習体験設計とデータ駆動 UI
- JSON ベースのチートシート・問題管理
- 問題一覧、詳細、解答表示切り替え、比較表示、ページング
- 学習コンテンツをコード変更なしで拡張する運用

### 7. コンピュータサイエンス基礎への接続
- レート制限における時間窓とカウンタ管理
- データ検証における型安全性と境界条件の考え方
- I/O、ファイルシステム、プロセス、ネットワークの連携理解
- システム設計におけるトレードオフ（簡潔さ vs 拡張性、性能 vs 安全性）

### 概念図
~~~mermaid
flowchart TD
  U[User Browser] --> N[NGINX Reverse Proxy]
  N --> A[PHP API / UI]
  A --> P[PlantUML Renderer]
  A --> T[(Temp Storage)]
  C[cron cleanup] --> T
  M[Monitor API] --> T
  A --> L[(Error Log)]

  subgraph Learning
    CS[CheatSheets JSON]
    PR[Problems JSON]
  end

  A --> CS
  A --> PR
~~~

## 必要条件
- Docker
- Docker Compose
- 8080 ポートが利用可能な環境

## インストール手順
1. リポジトリを取得します。

~~~bash
git clone <repository_url>
cd PlantUMLServer
~~~

2. コンテナをビルドして起動します。

~~~bash
docker compose up -d --build
~~~

3. サービス状態を確認します。

~~~bash
docker compose ps
~~~

## 使用方法
1. ブラウザで以下にアクセスします。
- http://localhost:8080/

2. API ヘルスチェックを行う場合。

~~~bash
curl -sS http://localhost:8080/api/health
~~~

3. レンダリング API の実行例。

~~~bash
curl -sS -o /tmp/render.svg -w '%{http_code}\n' \
  -X POST http://localhost:8080/api/render \
  -H 'Content-Type: application/json' \
  --data '{"uml":"@startuml\nA->B:test\n@enduml","format":"svg"}'
~~~

4. 一時ファイル監視 API の確認例。

~~~bash
curl -sS http://localhost:8080/api/temp-files/monitor
~~~

## 機能一覧
- UML 編集（Monaco Editor）
- SVG / PNG / TXT レンダリング
- ダウンロード用一時ファイル保存・削除
- チートシート表示
- 問題一覧・詳細・解答表示
- 比較表示・ページング
- 入力バリデーション
- レート制限
- エラーログ
- 一時ファイル監視
- cron cleanup
- セキュリティヘッダとリクエスト制限

## 技術スタック
- Frontend: HTML, CSS, JavaScript, Monaco Editor
- Backend: PHP 8.3 (built-in server)
- Reverse Proxy: NGINX
- Renderer: plantuml/plantuml-server
- Container: Docker, Docker Compose
- Data: JSON
- Ops: cron, structured logging

## 追加資料
- 要件定義・設計: [diagrams/requirement.md](diagrams/requirement.md)
- AWS 参考メモ: [diagrams/deploy.md](diagrams/deploy.md)
- デプロイ手順書: [diagrams/deploy_procedure.md](diagrams/deploy_procedure.md)
- 実装計画: [diagrams/plan.md](diagrams/plan.md)

## 貢献方法
1. Issue を作成して課題や提案内容を共有してください。
2. ブランチを作成して修正を実装してください。
3. 動作確認を行ったうえで Pull Request を作成してください。

推奨フロー:
~~~bash
git checkout -b feature/your-topic
# 変更
# テスト・動作確認
git add .
git commit -m "Add: your change"
git push origin feature/your-topic
~~~

## ライセンス
MIT License
