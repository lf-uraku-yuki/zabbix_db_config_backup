# zabbix_db_config_backup

Zabbix DB Config Only Backup Tool

Zabbixの監視ログデータを含んだ重たいデータベースから、
ホスト、アイテム、トリガー等の、監視設定のみをバックアップするツールです。
cronで定期実行するようにしておくと何かあった際にロールバックが可能です。

ロールバックを実行すると、その日時までの各種監視ログは
不整合の発生を防ぐために消去されます。
監視設定の初期段階や、取り返しのつかない設定ミスを取り消す際にのみ利用すべきです。

また、データの損傷が起きても許容できる環境で、
実際の動作の確認を行った上でご利用ください。

## ライセンス - License

MIT License.

## 実行環境 - Server Requirements

- Zabbix Server 3.0.10 and 3.4.3 and 4.0.2 (Not Supported 3.2.x !)
- MySQL or MariaDB
- PHP 5.6 or later
    - php-pdo
    - php-mysqlnd
    - php-mbstring
- zcat
- mysqldump

## 使い方 - How to use

First set the script "User Configuration"

backup

`php zabbix_config_backup.php --backup`

rollback

`php zabbix_config_backup.php --rollback ./config_backup/xxxxxx.sql.gz`

## 注意点 - Important

rollback を実行すると、その日時までの監視ログは消失します。  
If you execute rollback, the monitoring log until that datetime will be lost.

必ず動作検証を行ったうえで利用してください。  
Be sure to perform operation verification before using.
