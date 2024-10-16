curl -X POST http://localhost:8000/donation.php \
     -H "Content-Type: application/json" \
     -d '{
           "name": "",
           "bank_info": "",
           "amount": -100,
           "description": ""
         }'