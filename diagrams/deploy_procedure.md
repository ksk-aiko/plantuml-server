# PlantUMLServer デプロイ手順

## 1. 目的
この手順は、PlantUMLServer を Docker Compose で再現可能にデプロイし、動作確認とロールバックまで実施できる状態を作るための運用ドキュメントです。

## 2. 前提条件
- Docker が利用可能であること
- Docker Compose が利用可能であること
- 8080 ポートが利用可能であること
- リポジトリを配置済みであること

確認コマンド:

```bash
docker --version
docker compose version
```

## 3. ディレクトリ構成
対象ルート:

```text
PlantUMLServer/
	docker-compose.yml
	nginx/default.conf
	app/
		Dockerfile
		public/index.php
		scripts/cleanup-temp-files.sh
		scripts/entrypoint.sh
		crontabs/root
```

## 4. 初回デプロイ手順
1. 作業ディレクトリへ移動

```bash
cd /path/to/PlantUMLServer
```

2. イメージをビルドして起動

```bash
docker compose up -d --build
```

3. コンテナ状態を確認

```bash
docker compose ps
```

期待値:
- `nginx` が `Up`
- `app` が `Up`
- `plantuml` が `Up`

## 5. リリース更新手順
1. 最新コードを取得

```bash
git pull
```

2. 再ビルド・再起動

```bash
docker compose up -d --build
```

3. ヘルスチェック

```bash
curl -sS http://localhost:8080/api/health
```

期待値:

```json
{"status":"ok","service":"plantuml-mvp-api"}
```

## 6. 動作確認チェックリスト
1. ルート応答確認

```bash
curl -sSI http://localhost:8080/
```

確認ポイント:
- ステータス 200
- セキュリティヘッダ

2. レンダリング確認

```bash
curl -sS -o /tmp/render.svg -w '%{http_code}\n' \
	-X POST http://localhost:8080/api/render \
	-H 'Content-Type: application/json' \
	--data '{"uml":"@startuml\nA->B:test\n@enduml","format":"svg"}'
```

3. 一時ファイル監視確認

```bash
curl -sS http://localhost:8080/api/temp-files/monitor
```

4. cron 稼働確認

```bash
docker compose exec -T app sh -lc "ps | grep crond | grep -v grep"
```

## 7. ログ確認
1. コンテナログ

```bash
docker compose logs --tail=100 nginx
docker compose logs --tail=100 app
docker compose logs --tail=100 plantuml
```

2. アプリエラーログ

```bash
docker compose exec -T app sh -lc "tail -n 50 /tmp/plantuml_error.log"
```

3. cleanupログ

```bash
docker compose exec -T app sh -lc "tail -n 50 /tmp/plantuml_cleanup.log"
```

## 8. ロールバック手順
1. 直前コミットへ戻す

```bash
git log --oneline -n 5
git checkout <rollback_commit_hash>
```

2. 再デプロイ

```bash
docker compose up -d --build
```

3. ヘルスチェックを再実行

```bash
curl -sS http://localhost:8080/api/health
```

## 9. 障害時の一次切り分け
1. コンテナ死活

```bash
docker compose ps
```

2. nginx 設定反映確認

```bash
docker compose exec -T nginx nginx -T | sed -n '1,220p'
```

3. PHP 構文確認

```bash
docker compose exec -T app php -l /var/www/html/public/index.php
```

4. PlantUML 上流確認

```bash
docker compose exec -T app sh -lc "wget -qO- http://plantuml:8080/ || true"
```

## 10. 運用メモ
- PlantUML 起動直後は一時的に `502` が発生することがあるため、ウォームアップ後に再確認する
- デプロイ後は `api/health` と `api/render` の両方を確認する
- `diagrams/plan.md` は実測完了後にのみチェック更新する
