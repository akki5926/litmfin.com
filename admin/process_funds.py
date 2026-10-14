import json
from datetime import datetime
from time import sleep
from fetch_single_fund import fetch_fund_data

INPUT = "../data/fund_list.json"
OUTPUT = "../data/fund_output.json"

month_tag = datetime.now().strftime("%b-%Y")

with open(INPUT) as f:
    funds = json.load(f)

results = []

for i, fund in enumerate(funds):
    isin = fund["isin"]

    print(f"{i+1}/{len(funds)} {isin}")

    try:
        data = fetch_fund_data(isin)

        status = "success"
        if not data["top_holdings"] and not data["sector_allocation"]:
            status = "partial"

        result = {
            "isin": isin,
            "scheme_name": fund.get("scheme_name"),
            "month": month_tag,
            **data,
            "status": status
        }

    except Exception as e:
        result = {
            "isin": isin,
            "scheme_name": fund.get("scheme_name"),
            "month": month_tag,
            "status": "failed",
            "error": str(e)
        }

    results.append(result)
    sleep(0.4)

with open(OUTPUT, "w") as f:
    json.dump(results, f, indent=4)

print("Batch completed")