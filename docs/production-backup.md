# Produktionsbackup före driftsättning

Produktionsflödet tar en databasdump innan migreringar körs och laddar upp den som en krypterad GitHub Actions-artefakt med namnet `workflow-production-before-<commit>`. Artefakten sparas i 30 dagar. Den privata nyckeln för dekryptering ska förvaras utanför Git och webbservern.

För att läsa en nedladdad backup, placera den privata nyckeln och båda `.enc`-filerna i en skyddad katalog och kör:

```bash
openssl pkeyutl -decrypt -inkey production-backup-private.pem \
  -pkeyopt rsa_padding_mode:oaep -pkeyopt rsa_oaep_md:sha256 \
  -in backup-passphrase.enc -out backup-passphrase
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
  -pass file:backup-passphrase \
  -in workflow-before-deploy.sql.enc -out workflow-before-deploy.sql
```

Kontrollera dumpen innan återställning. Radera den dekrypterade dumpen och passfrasfilen när de inte längre behövs.
