from yahooquery import Ticker, search
import pandas as pd

# simple in-memory cache (per run)
symbol_cache = {}

def get_symbol(isin):
    if isin in symbol_cache:
        return symbol_cache[isin]

    results = search(isin)
    symbol = results['quotes'][0]['symbol']

    symbol_cache[isin] = symbol
    return symbol


def fetch_fund_data(isin):
    symbol = get_symbol(isin)
    fund = Ticker(symbol)

    result = {
        "top_holdings": [],
        "sector_allocation": {},
        "trailing_returns": {},
        "risk_statistics": {}
    }

    # --- TOP HOLDINGS ---
    df_top = fund.fund_top_holdings
    if isinstance(df_top, pd.DataFrame) and not df_top.empty:
        for _, row in df_top.iterrows():
            result["top_holdings"].append({
                "name": row.get("holdingName"),
                "percent": round(row.get("holdingPercent", 0) * 100, 2)
            })

    # --- SECTOR ---
    sector = fund.fund_sector_weightings
    if isinstance(sector, pd.DataFrame) and not sector.empty:
        for idx, val in sector.iterrows():
            result["sector_allocation"][idx] = round(val.iloc[0] * 100, 2)

    # --- PERFORMANCE ---
    perf = fund.fund_performance
    if symbol in perf:
        pdata = perf[symbol]

        tr = pdata.get("trailingReturns", {})
        result["trailing_returns"] = {
            "1Y": round(tr.get("oneYear", 0) * 100, 2),
            "3Y": round(tr.get("threeYear", 0) * 100, 2),
            "5Y": round(tr.get("fiveYear", 0) * 100, 2)
        }

        # --- RISK (your correct logic) ---
        risk_list = pdata.get('riskOverviewStatistics', {}).get('riskStatistics', [])
        if risk_list:
            df_risk = pd.DataFrame(risk_list).set_index('year')

            for yr in ['1y', '3y', '5y']:
                if yr in df_risk.index:
                    row = df_risk.loc[yr]
                    result["risk_statistics"][yr.upper()] = {
                        "alpha": row.get("alpha"),
                        "beta": row.get("beta"),
                        "stdDev": row.get("stdDev")
                    }

    return result