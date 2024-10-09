curl -X POST http://localhost:8000/donation.php \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Jane Smith",
           "bank_info": "Chase Bank, Account No: 987654321",
           "amount": 150.75,
           "description": "Supporting local charity initiatives."
         }'
