# PlantUML Server 要件定義・設計書

## 1. 要件整理

前回の内容を、要件定義・アーキテクチャ・技術選定・図・実装計画まで含めて、1つのMarkdownドキュメントとして統合します。

## 2. 要件定義書

# PlantUML Server 要件定義書

## 1. システム概要

### システム名
PlantUML Learning & Rendering Platform

### 目的
ユーザーが PlantUML 構文を利用して、ブラウザ上で UML 図を作成・確認・学習・出力できる Web サービスを提供する。

### 対象ユーザー
- ソフトウェアエンジニア
- UML 学習者
- システム設計者
- 学生
- アーキテクト

## 2. 機能要件

### 2.1 UML エディタ
- Monaco Editor を利用する
- PlantUML 構文を入力できる
- 行番号を表示できる
- 将来的にシンタックスハイライト、自動補完に対応する

### 2.2 UML プレビュー
- 入力された PlantUML をリアルタイムにプレビューする
- SVG、PNG、ASCII 形式で表示できる
- 構文エラー時はエラー内容を表示する

### 2.3 対応図種
- ユースケース図
- クラス図
- アクティビティ図
- 状態図
- シーケンス図
- マインドマップ
- ガント図
- 将来的にコンポーネント図、デプロイメント図を追加可能にする

### 2.4 ダウンロード機能
- .png 形式でダウンロードできる
- .svg 形式でダウンロードできる
- .txt 形式で PlantUML ソースをダウンロードできる
- 生成ファイルは一時保存し、一定時間後に削除する

### 2.5 チートシート機能
- 図種別の構文サンプルを表示する
- サンプルコードをコピーできる
- 初学者が構文を理解できる説明を表示する

### 2.6 プラクティスプロンプトジェネレータ
- JSON ファイルから問題データを読み込む
- 問題一覧を動的に表示する
- 問題詳細ページを動的に生成する
- Editor、Preview、Answer を表示する
- ユーザー回答と正解例を比較できる
- Answer の表示/非表示を切り替えられる

### 2.7 問題データ形式

例：

~~~json
{
  "id": 5,
  "title": "ライフラインの活性化",
  "theme": "シーケンス図",
  "uml": "@startuml\nautoactivate on\nalice -> bob : hello\nbob -> bob : self call\nbill -> bob #005500 : hello from thread 2\nbob -> george ** : create\nreturn done in thread 2\nreturn rc\nbob -> george !! : delete\nreturn success\n@enduml"
}
~~~

## 3. 非機能要件

### 3.1 拡張性
- 新しい図種を容易に追加できる
- チートシートや問題データは JSON 追加で拡張できる
- 図種ごとの設定をモジュール化する

### 3.2 ストレージ効率
- 生成ファイルは永続保存しない
- 一時ディレクトリに保存する
- cron またはリクエスト時クリーンアップで削除する
- 不要ファイルの残存を防ぐ

### 3.3 性能
- 通常サイズの UML は数秒以内にプレビューする
- 入力ごとのレンダリングは debounce で制御する
- 過剰な連続リクエストを防止する

### 3.4 セキュリティ
- 入力サイズを制限する
- 一時ファイル名は推測困難にする
- ディレクトリトラバーサルを防止する
- サーバ上の任意ファイル参照を禁止する
- PlantUML 実行環境の権限を最小化する

### 3.5 可用性・運用性
- 本番環境で安定稼働できる
- エラーログを取得できる
- 一時ファイル削除状況を監視できる
- サーバ障害時に復旧しやすい構成とする

## 4. API 要件

### 4.1 UML レンダリング API

#### Endpoint
POST /api/render

#### Request
~~~json
{
  "uml": "@startuml\nAlice -> Bob: Hello\n@enduml",
  "format": "svg"
}
~~~

#### Response
- SVG の場合: image/svg+xml
- PNG の場合: image/png
- TXT の場合: text/plain

### 4.2 問題一覧 API

#### Endpoint
GET /api/problems

#### Response
~~~json
[
  {
    "id": 5,
    "title": "ライフラインの活性化",
    "theme": "シーケンス図"
  }
]
~~~

### 4.3 問題詳細 API

#### Endpoint
GET /api/problems/{id}

#### Response
~~~json
{
  "id": 5,
  "title": "ライフラインの活性化",
  "theme": "シーケンス図",
  "uml": "@startuml..."
}
~~~

## 5. データモデル

### Problem

| Field | Type | Description |
|---|---|---|
| id | number | 問題ID |
| title | string | 問題タイトル |
| theme | string | 図種 |
| uml | text | 正解例 PlantUML |

### RenderRequest

| Field | Type | Description |
|---|---|---|
| uml | text | PlantUML ソース |
| format | string | svg / png / txt |

## 6. 制約条件

- プラットフォームは Web ブラウザ
- フロントエンドは HTML/CSS/JavaScript
- バックエンドは PHP
- エディタは Monaco Editor
- ストレージはサーバ上のローカル一時ファイル
- 生成ファイルは永続保存しない

## 7. 将来拡張

- ユーザーアカウント
- 図の保存
- 履歴管理
- GitHub 連携
- AI による UML 補完
- 共同編集
- コンポーネント図・デプロイメント図の強化

## 3. アーキテクチャ設計

### アーキテクチャスタイル
SPA + PHP API + PlantUML Renderer 構成とする。

### 構成要素

| Component | Responsibility |
|---|---|
| Web UI | ユーザー操作、画面表示 |
| Monaco Editor | PlantUML 入力 |
| Preview Panel | SVG/PNG/ASCII 表示 |
| CheatSheet Module | 構文サンプル表示 |
| Practice Module | 問題一覧、問題詳細、正解表示 |
| PHP API Server | レンダリング受付、問題取得 |
| PlantUML Renderer | UML 画像生成 |
| Temp Storage | 一時ファイル保存 |
| Cleanup Process | 一時ファイル削除 |

### データフロー
1. ユーザーが Monaco Editor に PlantUML を入力する
2. フロントエンドが debounce 後に PHP API へ送信する
3. PHP API が PlantUML Renderer に変換を依頼する
4. Renderer が SVG/PNG/ASCII を生成する
5. PHP API が結果をブラウザへ返却する
6. 一時ファイルは処理後または一定時間後に削除する

## 4. 技術選定理由

| 項目 | 技術 | 理由 |
|---|---|---|
| Platform | Web Browser | インストール不要で利用可能 |
| Frontend | HTML/CSS/JavaScript | 要件に合致し軽量 |
| Editor | Monaco Editor | VS Code に近い編集体験 |
| Backend | PHP | 指定技術であり小規模APIに適する |
| Renderer | PlantUML Server | PlantUML 図生成の標準的選択肢 |
| Storage | Local Temp Storage | 永続保存不要で低コスト |
| Data | JSON | 問題追加が容易 |

## 5. mermaid図

### 5.1 システム構成図

~~~mermaid
graph TD
  User[User Browser] --> UI[Web UI]
  UI --> Editor[Monaco Editor]
  UI --> Preview[Preview Panel]
  UI --> CheatSheet[CheatSheet Module]
  UI --> Practice[Practice Module]

  Editor --> API[PHP API Server]
  Practice --> API

  API --> Renderer[PlantUML Renderer]
  Renderer --> API

  API --> Temp[(Temp Storage)]
  Cleanup[Cleanup Process] --> Temp

  API --> Preview
~~~

### 5.2 コンポーネント図

~~~mermaid
graph LR
  Frontend[Frontend]
  Backend[PHP Backend]
  PlantUML[PlantUML Renderer]
  ProblemJSON[Problem JSON]
  TempStorage[(Temporary Files)]

  Frontend --> Backend
  Backend --> PlantUML
  Backend --> ProblemJSON
  Backend --> TempStorage
~~~

### 5.3 レンダリングシーケンス図

~~~mermaid
sequenceDiagram
  participant User
  participant Frontend
  participant API
  participant Renderer
  participant Temp

  User->>Frontend: PlantUML入力
  Frontend->>API: POST /api/render
  API->>Renderer: UML変換要求
  Renderer-->>API: SVG/PNG/ASCII
  API->>Temp: 必要時のみ一時保存
  API-->>Frontend: 描画結果返却
  Frontend-->>User: プレビュー表示
  API->>Temp: 一時ファイル削除
~~~

### 5.4 Practice 機能フロー

~~~mermaid
graph TD
  JSON[Problem JSON] --> List[Problem List Page]
  List --> Detail[Problem Page]
  Detail --> Editor[Editor]
  Detail --> Preview[Preview]
  Detail --> Answer[Answer View]
  Editor --> Preview
  Answer --> Compare[Compare View]
~~~
