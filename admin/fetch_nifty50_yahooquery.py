import yfinance as yf
import json
import time
from datetime import datetime, timedelta

# -----------------------------
# CONFIG
# -----------------------------

# Map which method to use
TICKER_METHOD = {
    "^NSEI"            : "download",
    "^CRSLDX"          : "download",
    "^CRSMID"          : "download",
    "NIFTYSMLCAP100.NS": "ticker",   # fallback method
}

tickers = {
    "^NSEI"            : "nifty50",
    "^CRSLDX"          : "nifty500",
    "^CRSMID"          : "nifty_midcap100",
    "NIFTYSMLCAP100.NS": "nifty_smallcap100",
}

# -----------------------------
# DATE RANGE (Last 60 days)
# -----------------------------
end_date = datetime.now()
start_date = end_date - timedelta(days=60)

# -----------------------------
# RETRY FUNCTION
# -----------------------------
def fetch_with_retry(fetch_func, retries=3, delay=3):
    for i in range(retries):
        try:
            return fetch_func()
        except Exception as e:
            print(f"  Retry {i+1} due to error: {e}")
            time.sleep(delay)
    return None

# -----------------------------
# MAIN FETCH LOGIC
# -----------------------------
pivoted = {}

for yahoo_symbol, db_col in tickers.items():
    print(f"Fetching {yahoo_symbol} ({db_col})...")

    try:
        method = TICKER_METHOD.get(yahoo_symbol, "download")

        if method == "ticker":
            df = fetch_with_retry(lambda: yf.Ticker(yahoo_symbol).history(
                start=start_date.strftime("%Y-%m-%d"),
                end=end_date.strftime("%Y-%m-%d")
            ))
        else:
            df = fetch_with_retry(lambda: yf.download(
                yahoo_symbol,
                start=start_date.strftime("%Y-%m-%d"),
                end=end_date.strftime("%Y-%m-%d"),
                auto_adjust=True,
                progress=False
            ))

        if df is None or df.empty:
            print(f"  WARNING: No data returned for {yahoo_symbol} — skipping.")
            continue

        print(f"  Fetched {len(df)} rows.")

        for date_index, row in df.iterrows():
            date_str = date_index.date().strftime("%Y-%m-%d")

            if date_str not in pivoted:
                pivoted[date_str] = {
                    "trade_date"        : date_str,
                    "nifty50"           : None,
                    "nifty500"          : None,
                    "nifty_midcap100"   : None,
                    "nifty_smallcap100" : None,
                }

            # FIXED: Correct way to access Close value
            try:
                close_val = float(row['Close'].iloc[0]) if hasattr(row['Close'], 'iloc') else float(row['Close'])
                pivoted[date_str][db_col] = round(close_val, 2)
            except Exception as e:
                print(f"  ERROR parsing row for {yahoo_symbol}: {e}")

        # Delay to avoid Yahoo rate limiting
        time.sleep(2)

    except Exception as e:
        print(f"  ERROR fetching {yahoo_symbol}: {e}")
        continue

# -----------------------------
# FINAL OUTPUT
# -----------------------------
data_list = list(pivoted.values())

if not data_list:
    print("No valid data fetched. Exiting.")
    exit()

# Sort by date
data_list.sort(key=lambda x: x["trade_date"])

# Save JSON
output_file = "temp_nifty50_data.json"

with open(output_file, "w") as f:
    json.dump(data_list, f, indent=4)

print(f"\nDone. {len(data_list)} date rows written to {output_file}")
