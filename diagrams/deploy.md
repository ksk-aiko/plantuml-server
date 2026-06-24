# 現在の構成

## インフラ
- AWS EC2 (Ubuntu)
- Docker + Docker Compose
- NGINXをリバースプロキシとして使用
- Let's Encrypt による HTTPS 化済み
- cron による証明書自動更新設定済み
- GitHub Machine User による private repository clone 構成済み

## ディレクトリ構造

/opt/projects
├ docker-compose.yml
├ nginx/
│   └ conf.d/
│       ├ deepsea-website.conf
│       └ resume-website.conf
├ deepsea-website/
├ resume-website/
└ certbot/

## 稼働中のサービス

サブドメイン方式：

- https://deepsea.kskaiko-soft.dev → deepsea-website コンテナ
- https://resume.kskaiko-soft.dev → resume-website コンテナ

すべて：

- 同一EC2
- 同一NGINXコンテナ
- 同一443ポート

で、NGINXのserver_nameとproxy_passでルーティングしています。

## HTTPS

- Let's Encrypt使用
- certbotコンテナで証明書発行
- /etc/letsencrypt をvolume共有
- cronで自動renew
- nginx reloadで無停止更新

## Docker構成

入口：

- nginxコンテナ（80,443公開）

内部：

- deepsea-websiteコンテナ（静的サイト）
- resume-websiteコンテナ（静的サイト）

すべてDocker内部ネットワークで通信

proxy_pass は service name を使用：

proxy_pass http://resume-website;

## GitHub構成

- Machine User使用
- SSH鍵認証
- private repository clone可能

---

# 私の現在の目標

この構成をベースに、

- 新しいサービスを効率よく追加できるテンプレート化
- より本番レベルの構成への改善
- 自動デプロイやスケーラブル構成

を進めたいです。

---

# 支援してほしいこと

以下の方針で支援してください：

- スモールステップで進める
- 理解確認の質問を適度に行う
- なぜそうするのか（原理）も説明する
- 実務レベルのベストプラクティスを提示する
- 不要な一般論は省く

---

