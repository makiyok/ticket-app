@AGENTS.md

# まちチケ 

{{ 説明 : 主催者がイベントと券種を登録し、参加者が Stripe Checkout でチケットを買うアプリ }}

## このプロジェクトの決まり

- 画面は Livewire 4 の単一ファイルコンポーネント（`resources/views/pages/**/⚡*.blade.php`）で作る。Volt は使わない
- UI は Flux UI の Free 版のコンポーネントだけを使う（Pro 専用は使わない）。無いものは Tailwind で作る
- 文言は日本語。日付は `isoFormat('M月D日(ddd) HH:mm')`、金額は「3,000円」の形式
- 認可は `app/Policies/` のポリシーで行い、画面側では `$this->authorize()` を使う
- 外部サービス（Stripe など）に触るコードは `app/Services/` に閉じ込める。テストではそのクラスをモックする
- 機能を追加したら `tests/Feature/` に Pest テストを追加し、`/check` が通ることを確認する
- 開発コマンドは Sail 経由（`sail artisan ...`、`sail composer ...`、`sail npm ...`）。ホストの php や composer は使わない
- ビューを追加・変更したら `sail npm run build` を実行する（Tailwind がクラスを拾うため）

## 作業の進め方

- 変更の前に、何をどのファイルに書くかを短く説明してから実装する
- 1回の指示で扱う範囲は1機能まで。大きい依頼は分割案を先に出す
- 既存のファイルの書き方に合わせる（同じ階層のファイルを先に読む）
- `.env` の値、API キー、パスワードはコードやコミットに含めない
- 失敗したコマンドは、原因の推測と対処案を1つずつ示してから再実行する

## 設計

詳細は [doc/design/database.md](doc/design/database.md) と [doc/design/screens.md](doc/design/screens.md)。テーブル、カラム、URL を追加・変更するときは先にこの2つを読み、設計を変えたらファイルも更新する。

### 用語

- イベント（`events`）: 主催者が登録する催し。`status` は `draft` / `published`
- 券種（`ticket_types`）: イベントごとのチケットの種類。価格、定員、販売期間を持つ
- 注文（`orders`）と明細（`order_items`）: 1回の購入と、その中の券種ごとの行。`status` は `pending` / `paid`
- チケット（`tickets`）: 支払い済みになったときに枚数分発行する。1枚ごとに固有の `code` を持つ

### データベース

- ロールは `users.role`（`customer` / `organizer` / `admin`）。状態やロールは string カラムを PHP の Enum にキャストする
- 主催者は `events.organizer_id`、注文者は `orders.user_id`。どちらも `users.id` を指すので取り違えない
- 金額は円の整数。注文時の単価を `order_items.unit_price` に保存し、合計は券種の現在の価格から計算し直さない
- 残り枚数はカラムに持たない。`capacity −（paid の枚数）−（作成から15分以内の pending の枚数）` で計算する
- 注文を作る前に、販売期間内であること、枚数が残り枚数以下であること、枚数が 1〜10 であることを確認する（`OrderService::createOrder()`）
- 外部キーは、お金に関わる記録（注文、売れた券種）が restrict、親と一体のもの（券種、明細、チケット）が cascade
- 注文があるイベントや券種は削除できない。DB エラーになる前に画面側で断る
- 画像は `public` ディスクの `events/` に保存する

### 画面

- 誰でも見られるのは `/`（イベント一覧）と `/events/{event}`（詳細）だけ。一覧に出すのは公開中かつ開催前のイベント
- 購入はログインユーザー全員ができる（主催者も買える）
- 主催者向けの画面は `/dashboard` と `/organizer/...`。イベントの編集や注文一覧は自分のイベントだけ
- 注文の画面（`/orders/{order}`）は注文者本人だけが見られる
