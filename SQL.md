# Various SQL scripts to fix bugs

## Parent chaining issue

### Fix the issue with parent chaining 

    update wp_furik_transactions as a SET parent=(select id from (select * from wp_furik_transactions) as d1 WHERE recurring=1 and id < a.id ORDER BY id DESC limit 1) WHERE parent is not null and recurring is null and parent not in (select id from (select * from wp_furik_transactions) as d2 WHERE recurring=1)

### Verify it worked well

This should return empty results:

    SELECT * FROM `wp_furik_transactions` as a WHERE parent is not null and email != (select email from wp_furik_transactions where id=a.parent)

### Database upgrade after adding the transaction_time column

    UPDATE wp_furik_transactions SET transaction_time=time WHERE transaction_status != 6;