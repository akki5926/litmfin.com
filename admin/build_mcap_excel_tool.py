import pandas as pd
import json
import os
from datetime import datetime
from xlsxwriter.utility import xl_rowcol_to_cell

import sys
import random

# 1. Path Management
current_dir = os.path.dirname(os.path.abspath(__file__))
json_file = os.path.join(current_dir, 'temp_fund_data.json')

# Accept optional months argument as first arg (comma separated YYYY-mm-01)
months_arg = None
if len(sys.argv) > 1:
    months_arg = sys.argv[1]

# File name can be provided as the last argument (PHP caller passes months, a flag, then filename)
if len(sys.argv) > 1:
    possible_file = sys.argv[-1]
    if possible_file.lower().endswith(('.xlsx', '.xls')):
        file_name = possible_file
    else:
        file_name = os.path.join(current_dir, "Market_Cap_Allocation_Tool_filled_v5.xlsx")
else:
    file_name = os.path.join(current_dir, "Market_Cap_Allocation_Tool_filled_v5.xlsx")

if not os.path.exists(json_file):
    print("Error: JSON data not found.")
    exit()

with open(json_file, 'r') as f:
    df = pd.DataFrame(json.load(f))

# 2. Setup Headers
selected_months = []
if months_arg and months_arg.strip():
    selected_months = [m.strip() for m in months_arg.split(',') if m.strip()]

existing_months = []
if 'mcap_data' in df.columns:
    for item in df['mcap_data']:
        if isinstance(item, dict):
            existing_months.extend(item.keys())

existing_months = [m for m in existing_months if isinstance(m, str)]
existing_dates = []
for m in existing_months:
    try:
        existing_dates.append(datetime.strptime(m, '%Y-%m-%d'))
    except Exception:
        pass

last_filled = max(existing_dates) if existing_dates else None
selected_dates = []
for m in selected_months:
    try:
        selected_dates.append(datetime.strptime(m, '%Y-%m-%d'))
    except Exception:
        pass

end_date = None
if selected_dates:
    end_date = max(selected_dates)
elif last_filled:
    end_date = last_filled
else:
    end_date = datetime.now().replace(day=1)

# If the end date resolves to current month, exclude current month
if end_date.year == datetime.now().year and end_date.month == datetime.now().month:
    end_date = (end_date.replace(day=1) - pd.DateOffset(months=1))

month_headers = pd.date_range(start="2020-01-01", end=end_date, freq='MS')

with pd.ExcelWriter(file_name, engine='xlsxwriter') as writer:
    workbook = writer.book

    # --- Formats ---
    light_border = '#E9E9E9'

    month_header_fmt = workbook.add_format({
        'bold': True, 'bg_color': '#F2F9F2', 'border': 1,
        'border_color': light_border, 'align': 'center', 'num_format': 'mmm-yy'
    })

    fund_title_fmt = workbook.add_format({
        'bold': True, 'font_size': 11, 'bg_color': '#F2F7FC',
        'font_color': '#366092', 'border': 1, 'border_color': light_border
    })

    category_fmt = workbook.add_format({
        'bg_color': '#FAFAFA', 'border': 1,
        'border_color': light_border, 'bold': False
    })

    entry_fmt = workbook.add_format({
        'border': 1, 'border_color': light_border,
        'num_format': '0.00', 'align': 'center', 'bold': False
    })

    total_fmt = workbook.add_format({
        'bold': True, 'border': 1, 'bg_color': '#F5F5F5',
        'num_format': '0.00', 'align': 'center'
    })

    # --- SUMMARY TAB ---
    summary_ws = workbook.add_worksheet('Summary')
    summary_ws.write(0, 0, "AMC Name", month_header_fmt)
    summary_ws.write(0, 1, "ISIN", month_header_fmt)
    summary_ws.write(0, 2, "Fund Name", month_header_fmt)
    summary_ws.write(0, 3, "Months Filled", month_header_fmt)
    summary_ws.write(0, 4, "Status", month_header_fmt)
    
    summary_ws.set_column('A:A', 25)
    summary_ws.set_column('B:B', 15)
    summary_ws.set_column('C:C', 45)
    summary_ws.set_column('D:E', 15)
    
    summary_row = 1

    # --- AMC TABS ---
    for amc_name, group in df.groupby('amc_name'):

        # ✅ FIX: Proper AMC naming
        clean_amc = (
            str(amc_name).strip() if pd.notna(amc_name) and str(amc_name).strip()
            else "uncategorized"
        )[:31].translate(str.maketrans("", "", "[]:*?/\\"))
        
        worksheet = workbook.add_worksheet(clean_amc)
        worksheet.set_column('A:A', 25)

        curr_row = 0

        for _, fund in group.iterrows():
            fname = str(fund['scheme_name']) if fund['scheme_name'] else "Unnamed Fund"

            # ❌ REMOVED: no unnecessary write to (row,0)

            # Header Row 1
            info_text = f" FUND: {fname}  |  ISIN: {fund['isin']}  |  CODE: {fund['scheme_code']}"
            worksheet.merge_range(curr_row, 0, curr_row, len(month_headers), info_text, fund_title_fmt)

            # Header Row 2
            anchor_text = f"ID: {fund['isin']}"
            worksheet.write(curr_row + 1, 0, anchor_text, month_header_fmt)

            for col_num, month in enumerate(month_headers):
                worksheet.write(curr_row + 1, col_num + 1, month, month_header_fmt)

            # Categories
            categories = ['Large Cap', 'Mid Cap', 'Small Cap', 'Cash', 'Debt']
            
            mcap_data = fund.get('mcap_data', {})

            for i, cat in enumerate(categories):
                r_idx = curr_row + i + 2
                worksheet.write(r_idx, 0, cat, category_fmt)

                for col_num, month in enumerate(month_headers):
                    c_idx = col_num + 1

                    # Populate actual database data if it exists, otherwise leave empty
                    month_str = month.strftime('%Y-%m-01')
                    
                    if isinstance(mcap_data, dict):
                        month_data = mcap_data.get(month_str, {})
                        val = month_data.get(cat) if isinstance(month_data, dict) else None
                    else:
                        val = None
                    
                    if val is not None and val != "":
                        worksheet.write_number(r_idx, c_idx, float(val), entry_fmt)
                    else:
                        worksheet.write(r_idx, c_idx, None, entry_fmt)

            # ✅ FIX: Apply single block-level data validation to prevent XML bloat & Excel corruption
            first_col = 1
            last_col = len(month_headers)
            first_row = curr_row + 2
            last_row = curr_row + 6

            tl_cell = xl_rowcol_to_cell(first_row, first_col)
            sum_start = xl_rowcol_to_cell(first_row, first_col, row_abs=True)
            sum_end = xl_rowcol_to_cell(last_row, first_col, row_abs=True)
            
            fmla = f'=AND(ISNUMBER({tl_cell}), {tl_cell}>=0, {tl_cell}<=100, SUM({sum_start}:{sum_end})<=100)'

            worksheet.data_validation(first_row, first_col, last_row, last_col, {
                'validate': 'custom',
                'value': fmla,
                'error_title': 'Entry Error',
                'error_message': 'Enter 0–100. Total per month cannot exceed 100%.'
            })

            # Total Row
            total_row_idx = curr_row + 7
            worksheet.write(total_row_idx, 0, "TOTAL %", total_fmt)

            for col_num in range(len(month_headers)):
                c_idx = col_num + 1
                fmla = f"=SUM({xl_rowcol_to_cell(curr_row+2, c_idx)}:{xl_rowcol_to_cell(curr_row+6, c_idx)})"
                worksheet.write_formula(total_row_idx, c_idx, fmla, total_fmt)

            # ✅ Safely log to the summary sheet explicitly by passing pandas
            month_count_range = f"'{clean_amc}'!{xl_rowcol_to_cell(total_row_idx, 1)}:{xl_rowcol_to_cell(total_row_idx, len(month_headers))}"
            
            summary_ws.write(summary_row, 0, clean_amc)
            summary_ws.write(summary_row, 1, fund['isin'])
            summary_ws.write(summary_row, 2, fname)
            summary_ws.write_formula(summary_row, 3, f'=COUNTIF({month_count_range}, ">0")')
            summary_ws.write_formula(summary_row, 4, f'=IF(D{summary_row+1}={len(month_headers)}, "Completed", IF(D{summary_row+1}>0, "In Progress", "Pending"))')

            curr_row += 10
            summary_row += 1

    print(f"File generated successfully at: {file_name}")