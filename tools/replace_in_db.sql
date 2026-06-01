UPDATE oc_product_description
SET
    description = REPLACE(description, 'wrong_text', 'correct_text')
WHERE
	description like '%wrong_text%'
    and language_id = 3;
	
	
select product_id, language_id, description, REPLACE(description, 'wrong_text', 'correct_text') updated_description
from oc_product_description
WHERE
	description like '%wrong_text%'
    and language_id = 3;