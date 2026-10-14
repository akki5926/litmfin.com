'use client';

import React, { useState, useEffect, useMemo } from 'react';
import TypedText from '@/components/TypedText';
import {
  Container,
  Box,
  Typography,
  Paper,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  CircularProgress,
  Alert,
  Card,
  CardContent,
  IconButton,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Tabs,
  Tab,
} from '@mui/material';
import Grid from '@mui/material/Grid';
import SearchIcon from '@mui/icons-material/Search';
import RefreshIcon from '@mui/icons-material/Refresh';
import DownloadIcon from '@mui/icons-material/Download';
import VisibilityIcon from '@mui/icons-material/Visibility';
import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import dayjs from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';

// Extend dayjs with custom parse format
dayjs.extend(customParseFormat);

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

export default function SIPsPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [viewDialogOpen, setViewDialogOpen] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [activeTab, setActiveTab] = useState(0);
  const [hasFetched, setHasFetched] = useState(false);

  // Data state
  const [sipData, setSipData] = useState({
    total: 0,
    transactions: []
  });

  // Fetch SIP transactions
  const fetchSIPs = async () => {
    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const token = localStorage.getItem('site_token');
      if (!token) {
        setError('Authentication token not found. Please login again.');
        setLoading(false);
        return;
      }

      const response = await fetch(`${API_BASE_URL}/sip_transactions_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        }
      });

      const apiResponse = await response.json();

      if (!response.ok || !apiResponse.success) {
        throw new Error(apiResponse.message || 'Failed to fetch data');
      }

      const responseData = apiResponse.data;
      let jsonData;

      if (typeof responseData.response === 'string') {
        try {
          jsonData = JSON.parse(responseData.response);
        } catch (parseError) {
          console.error('Error parsing response:', parseError);
          jsonData = responseData.json || { report_data: [], report_data_total: "0" };
        }
      } else {
        jsonData = responseData.json || responseData;
      }

      if (jsonData.response_status !== 'S') {
        throw new Error(jsonData.error_remark || 'Failed to fetch data');
      }

      const transformedData = {
        total: parseInt(jsonData.report_data_total) || 0,
        transactions: jsonData.report_data || []
      };

      setSipData(transformedData);
      setHasFetched(true);
    } catch (err) {
      console.error('Error fetching data:', err);
      setError(err.message || 'An error occurred while fetching data');
      setSipData({ total: 0, transactions: [] });
    } finally {
      setLoading(false);
    }
  };

  // Fetch data on page mount
  useEffect(() => {
    fetchSIPs();
  }, []);

  const handleRefresh = () => {
    fetchSIPs();
  };

  // Filter transactions based on active tab
  const filteredTransactions = useMemo(() => {
    const list = sipData.transactions || [];
    if (activeTab === 0) {
      return list.filter(t => !t.registration_type || t.registration_type.toUpperCase() === 'SIP');
    } else if (activeTab === 1) {
      return list.filter(t => t.registration_type && t.registration_type.toUpperCase() === 'SWP');
    } else if (activeTab === 2) {
      return list.filter(t => t.registration_type && t.registration_type.toUpperCase() === 'STP');
    }
    return [];
  }, [sipData.transactions, activeTab]);

  // Show tab-specific success message when tab changes or data is fetched
  useEffect(() => {
    if (hasFetched && !loading && !error) {
      const tabNames = ['SIP', 'SWP', 'STP'];
      const count = filteredTransactions.length;
      setSuccess(`Successfully fetched ${count} ${tabNames[activeTab]} records`);

      const timer = setTimeout(() => {
        setSuccess('');
      }, 3000); // clears message after 3 seconds

      return () => clearTimeout(timer);
    }
  }, [activeTab, sipData, loading, error, hasFetched, filteredTransactions.length]);

  // Memoized stats calculations based on selected tab
  const memoizedStats = useMemo(() => {
    if (!filteredTransactions || filteredTransactions.length === 0) {
      return {
        totalCount: 0,
        totalAmount: 0,
        activeCount: 0,
        totalUnitsTransfers: 0
      };
    }

    const activeCount = filteredTransactions.filter(t => t?.status?.toUpperCase() === 'ACTIVE').length;

    if (activeTab === 0) {
      const totalAmount = filteredTransactions.reduce((sum, t) => sum + (parseFloat(t?.installments_amount) || 0), 0);
      const totalUnitsTransfers = filteredTransactions.reduce((sum, t) => sum + (parseInt(t?.no_of_installments) || 0), 0);
      return {
        totalCount: filteredTransactions.length,
        totalAmount,
        activeCount,
        totalUnitsTransfers
      };
    } else if (activeTab === 1) {
      const totalAmount = filteredTransactions.reduce((sum, t) => sum + (parseFloat(t?.withdrawl_amount) || 0), 0);
      const totalUnitsTransfers = filteredTransactions.reduce((sum, t) => sum + (parseInt(t?.no_of_withdrawls) || 0), 0);
      return {
        totalCount: filteredTransactions.length,
        totalAmount,
        activeCount,
        totalUnitsTransfers
      };
    } else {
      const totalAmount = filteredTransactions.reduce((sum, t) => sum + (parseFloat(t?.transfer_amount) || 0), 0);
      const totalUnitsTransfers = filteredTransactions.reduce((sum, t) => sum + (parseInt(t?.no_of_transfers) || 0), 0);
      return {
        totalCount: filteredTransactions.length,
        totalAmount,
        activeCount,
        totalUnitsTransfers
      };
    }
  }, [filteredTransactions, activeTab]);

  // Format currency
  const formatCurrency = (amount) => {
    const numAmount = parseFloat(amount) || 0;
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(numAmount);
  };

  // Get status color
  const getStatusColor = (status) => {
    switch (status?.toUpperCase()) {
      case 'ACTIVE': return 'success';
      case 'MATURED': return 'info';
      case 'CXL': return 'error';
      case 'PENDING': return 'warning';
      default: return 'default';
    }
  };

  // Format date from API response (DD MMM YYYY) to DD/MM/YYYY
  const formatDateDisplay = (dateString) => {
    if (!dateString || dateString === ' ' || dateString === '') return 'N/A';

    try {
      const date = dayjs(dateString, 'DD MMM YYYY');
      return date.isValid() ? date.format('DD/MM/YYYY') : dateString;
    } catch (error) {
      return dateString;
    }
  };

  // Handle view details
  const handleViewDetails = (transaction) => {
    setSelectedTransaction(transaction);
    setViewDialogOpen(true);
  };

  // Close view dialog
  const handleCloseDialog = () => {
    setViewDialogOpen(false);
    setSelectedTransaction(null);
  };

  // Download data as CSV based on active tab
  const downloadCSV = () => {
    if (!filteredTransactions || filteredTransactions.length === 0) return;

    let headers = [];
    let rows = [];

    if (activeTab === 0) {
      headers = [
        'Scheme Name',
        'Amount',
        'Frequency',
        'Start Date',
        'End Date',
        'No. of Installments',
        'SIP Registration No',
        'SIP Registration Date',
        'Folio Number',
        'Status',
        'Mobile Number',
        'Email',
        'NSE Mandate ID',
        'AMC'
      ];
      rows = filteredTransactions.map(t => [
        `"${t?.scheme_name || ''}"`,
        `"${t?.installments_amount || ''}"`,
        `"${t?.frequency_type || ''}"`,
        `"${formatDateDisplay(t?.start_date)}"`,
        `"${formatDateDisplay(t?.end_date)}"`,
        `"${t?.no_of_installments || ''}"`,
        `"${t?.xsip_registration_no || ''}"`,
        `"${formatDateDisplay(t?.xsip_registration_date)}"`,
        `"${t?.folio_number || ''}"`,
        `"${t?.status || ''}"`,
        `"${t?.primary_holder_mobile || ''}"`,
        `"${t?.primary_holder_email || ''}"`,
        `"${t?.nse_mandate_id || ''}"`,
        `"${t?.amc_name || ''}"`
      ]);
    } else if (activeTab === 1) {
      headers = [
        'Scheme Name',
        'Withdrawal Amount',
        'Frequency',
        'Start Date',
        'End Date',
        'No. of Withdrawals',
        'SWP Registration No',
        'SWP Registration Date',
        'Folio Number',
        'Status',
        'Mobile Number',
        'Email',
        'Bank Account No',
        'AMC'
      ];
      rows = filteredTransactions.map(t => [
        `"${t?.scheme_name || ''}"`,
        `"${t?.withdrawl_amount || ''}"`,
        `"${t?.frequency_type || ''}"`,
        `"${formatDateDisplay(t?.swp_start_date)}"`,
        `"${formatDateDisplay(t?.swp_end_date)}"`,
        `"${t?.no_of_withdrawls || ''}"`,
        `"${t?.swp_registration_no || ''}"`,
        `"${formatDateDisplay(t?.swp_registration_date)}"`,
        `"${t?.folio_number || ''}"`,
        `"${t?.status || ''}"`,
        `"${t?.primary_holder_mobile || ''}"`,
        `"${t?.primary_holder_email || ''}"`,
        `"${t?.bank_account_no || ''}"`,
        `"${t?.amc_name || ''}"`
      ]);
    } else if (activeTab === 2) {
      headers = [
        'From Scheme Name',
        'To Scheme Name',
        'Transfer Amount',
        'Frequency',
        'Start Date',
        'End Date',
        'No. of Transfers',
        'STP Registration No',
        'STP Registration Date',
        'Folio Number',
        'Status',
        'Mobile Number',
        'Email',
        'From AMC',
        'To AMC'
      ];
      rows = filteredTransactions.map(t => [
        `"${t?.from_scheme_name || ''}"`,
        `"${t?.to_scheme_name || ''}"`,
        `"${t?.transfer_amount || ''}"`,
        `"${t?.frequency_type || ''}"`,
        `"${formatDateDisplay(t?.stp_start_date)}"`,
        `"${formatDateDisplay(t?.stp_end_date)}"`,
        `"${t?.no_of_transfers || ''}"`,
        `"${t?.stp_registration_no || ''}"`,
        `"${formatDateDisplay(t?.stp_registration_date)}"`,
        `"${t?.folio_number || ''}"`,
        `"${t?.status || ''}"`,
        `"${t?.primary_holder_mobile || ''}"`,
        `"${t?.primary_holder_email || ''}"`,
        `"${t?.from_amc_name || ''}"`,
        `"${t?.to_amc_name || ''}"`
      ]);
    }

    const csvContent = [
      headers.join(','),
      ...rows.map(r => r.join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const tabNames = ['sip', 'swp', 'stp'];
    a.download = `${tabNames[activeTab]}_transactions_${dayjs().format('YYYYMMDD_HHmmss')}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  // Render table cell content safely
  const renderTableCell = (content) => {
    return content && content !== ' ' && content !== '' ? content : 'N/A';
  };

  return (
    <Container maxWidth="xl" sx={{ py: 4 }}>
      <Typography variant="h4" gutterBottom sx={{ fontWeight: 'bold', color: 'primary.main', mb: 3 }}>
        <TypedText text="SIP / SWP / STP" className="" />
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }} onClose={() => setError('')}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mb: 3 }} onClose={() => setSuccess('')}>
          {success}
        </Alert>
      )}

      {/* Tabs Layout */}
      <Paper sx={{ mb: 4, overflow: 'hidden', boxShadow: 2, borderRadius: 2 }}>
        <Tabs
          value={activeTab}
          onChange={(e, newValue) => setActiveTab(newValue)}
          indicatorColor="primary"
          textColor="primary"
          variant="fullWidth"
          sx={{
            borderBottom: 1,
            borderColor: 'divider',
            '& .MuiTab-root': {
              fontWeight: 'bold',
              fontSize: '1rem',
              py: 2,
            }
          }}
        >
          <Tab label="SIP" />
          <Tab label="SWP" />
          <Tab label="STP" />
        </Tabs>
      </Paper>

      {/* Stats Summary */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ boxShadow: 2, borderRadius: 2 }}>
            <CardContent>
              <Typography color="textSecondary" gutterBottom fontWeight="medium">
                Total
              </Typography>
              <Typography variant="h4" fontWeight="bold">
                {memoizedStats.totalCount}
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ boxShadow: 2, borderRadius: 2 }}>
            <CardContent>
              <Typography color="textSecondary" gutterBottom fontWeight="medium">
                {activeTab === 0 ? 'Total SIP Amount' : activeTab === 1 ? 'Total SWP Amount' : 'Total STP Amount'}
              </Typography>
              <Typography variant="h4" color="primary.main" fontWeight="bold">
                {formatCurrency(memoizedStats.totalAmount)}
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ boxShadow: 2, borderRadius: 2 }}>
            <CardContent>
              <Typography color="textSecondary" gutterBottom fontWeight="medium">
                {activeTab === 0 ? 'Active SIPs' : activeTab === 1 ? 'Active SWPs' : 'Active STPs'}
              </Typography>
              <Typography variant="h4" color="success.main" fontWeight="bold">
                {memoizedStats.activeCount}
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ boxShadow: 2, borderRadius: 2 }}>
            <CardContent>
              <Typography color="textSecondary" gutterBottom fontWeight="medium">
                {activeTab === 0 ? 'Total Installments' : activeTab === 1 ? 'Total Withdrawals' : 'Total Transfers'}
              </Typography>
              <Typography variant="h4" fontWeight="bold">
                {memoizedStats.totalUnitsTransfers}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Export & Refresh Buttons */}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
        <Typography variant="subtitle1" color="textSecondary" fontWeight="medium">
          {activeTab === 0 ? 'Systematic Investment Plan List' : activeTab === 1 ? 'Systematic Withdrawal Plan List' : 'Systematic Transfer Plan List'}
        </Typography>
        <Box sx={{ display: 'flex', gap: 2 }}>
          <Button
            variant="outlined"
            startIcon={<RefreshIcon />}
            onClick={handleRefresh}
            disabled={loading}
          >
            Refresh
          </Button>
          <Button
            variant="contained"
            startIcon={<DownloadIcon />}
            onClick={downloadCSV}
            disabled={!filteredTransactions || filteredTransactions.length === 0}
          >
            Export CSV
          </Button>
        </Box>
      </Box>

      {/* Transactions Table */}
      <TableContainer component={Paper} sx={{ boxShadow: 3, borderRadius: 2 }}>
        <Table>
          <TableHead>
            {activeTab === 0 && (
              <TableRow sx={{ backgroundColor: 'primary.light' }}>
                <TableCell><strong>Scheme Name</strong></TableCell>
                <TableCell><strong>Amount</strong></TableCell>
                <TableCell><strong>Frequency</strong></TableCell>
                <TableCell><strong>Start Date</strong></TableCell>
                <TableCell><strong>End Date</strong></TableCell>
                <TableCell><strong>Installments</strong></TableCell>
                <TableCell><strong>SIP Reg Date</strong></TableCell>
                <TableCell><strong>Status</strong></TableCell>
                <TableCell><strong>Actions</strong></TableCell>
              </TableRow>
            )}
            {activeTab === 1 && (
              <TableRow sx={{ backgroundColor: 'primary.light' }}>
                <TableCell><strong>Scheme Name</strong></TableCell>
                <TableCell><strong>Withdrawal Amount</strong></TableCell>
                <TableCell><strong>Frequency</strong></TableCell>
                <TableCell><strong>Start Date</strong></TableCell>
                <TableCell><strong>End Date</strong></TableCell>
                <TableCell><strong>Withdrawals</strong></TableCell>
                <TableCell><strong>SWP Reg Date</strong></TableCell>
                <TableCell><strong>Status</strong></TableCell>
                <TableCell><strong>Actions</strong></TableCell>
              </TableRow>
            )}
            {activeTab === 2 && (
              <TableRow sx={{ backgroundColor: 'primary.light' }}>
                <TableCell><strong>From Scheme</strong></TableCell>
                <TableCell><strong>To Scheme</strong></TableCell>
                <TableCell><strong>Transfer Amount</strong></TableCell>
                <TableCell><strong>Frequency</strong></TableCell>
                <TableCell><strong>Start Date</strong></TableCell>
                <TableCell><strong>End Date</strong></TableCell>
                <TableCell><strong>Transfers</strong></TableCell>
                <TableCell><strong>Status</strong></TableCell>
                <TableCell><strong>Actions</strong></TableCell>
              </TableRow>
            )}
          </TableHead>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={9} align="center" sx={{ py: 8 }}>
                  <CircularProgress />
                  <Typography sx={{ mt: 2 }}>Loading...</Typography>
                </TableCell>
              </TableRow>
            ) : (!filteredTransactions || filteredTransactions.length === 0) ? (
              <TableRow>
                <TableCell colSpan={9} align="center" sx={{ py: 8 }}>
                  <Typography color="textSecondary">
                    No records found for this type.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : (
              filteredTransactions.map((transaction, index) => (
                <TableRow
                  key={index}
                  hover
                  sx={{
                    backgroundColor: index % 2 === 0 ? 'grey.50' : 'white',
                    '&:hover': {
                      backgroundColor: 'primary.lighter',
                    }
                  }}
                >
                  {activeTab === 0 && (
                    <>
                      <TableCell>
                        <Typography variant="body2" fontWeight="medium">
                          {renderTableCell(transaction?.scheme_name)}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography fontWeight="medium">
                          {formatCurrency(transaction?.installments_amount)}
                        </Typography>
                      </TableCell>
                      <TableCell>{renderTableCell(transaction?.frequency_type)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.start_date)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.end_date)}</TableCell>
                      <TableCell>{renderTableCell(transaction?.no_of_installments)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.xsip_registration_date)}</TableCell>
                    </>
                  )}
                  {activeTab === 1 && (
                    <>
                      <TableCell>
                        <Typography variant="body2" fontWeight="medium">
                          {renderTableCell(transaction?.scheme_name)}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography fontWeight="medium">
                          {formatCurrency(transaction?.withdrawl_amount)}
                        </Typography>
                      </TableCell>
                      <TableCell>{renderTableCell(transaction?.frequency_type)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.swp_start_date)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.swp_end_date)}</TableCell>
                      <TableCell>{renderTableCell(transaction?.no_of_withdrawls)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.swp_registration_date)}</TableCell>
                    </>
                  )}
                  {activeTab === 2 && (
                    <>
                      <TableCell>
                        <Typography variant="body2" fontWeight="medium">
                          {renderTableCell(transaction?.from_scheme_name)}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" fontWeight="medium">
                          {renderTableCell(transaction?.to_scheme_name)}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography fontWeight="medium">
                          {formatCurrency(transaction?.transfer_amount)}
                        </Typography>
                      </TableCell>
                      <TableCell>{renderTableCell(transaction?.frequency_type || transaction?.trxn_mode)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.stp_start_date)}</TableCell>
                      <TableCell>{formatDateDisplay(transaction?.stp_end_date)}</TableCell>
                      <TableCell>{renderTableCell(transaction?.no_of_transfers)}</TableCell>
                    </>
                  )}
                  <TableCell>
                    <Chip
                      label={renderTableCell(transaction?.status)}
                      size="small"
                      color={getStatusColor(transaction?.status)}
                    />
                  </TableCell>
                  <TableCell>
                    <Tooltip title="View Details">
                      <IconButton
                        size="small"
                        onClick={() => handleViewDetails(transaction)}
                      >
                        <VisibilityIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      {/* Pagination info */}
      {filteredTransactions && filteredTransactions.length > 0 && (
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 2 }}>
          <Typography color="textSecondary">
            Showing {filteredTransactions.length} records
          </Typography>
        </Box>
      )}

      {/* View Details Dialog */}
      <Dialog open={viewDialogOpen} onClose={handleCloseDialog} maxWidth="md" fullWidth>
        <DialogTitle sx={{ fontWeight: 'bold' }}>
          {selectedTransaction?.registration_type === 'SWP' 
            ? 'SWP Details' 
            : selectedTransaction?.registration_type === 'STP' 
              ? 'STP Details' 
              : 'SIP Details'}
        </DialogTitle>
        <DialogContent dividers>
          {selectedTransaction && (
            <Grid container spacing={2}>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Client Name</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.client_name)}</Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Client Code</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.client_code)}</Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Mobile Number</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.primary_holder_mobile)}</Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Email</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.primary_holder_email)}</Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Folio Number</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.folio_number)}</Typography>
              </Grid>

              {(!selectedTransaction.registration_type || selectedTransaction.registration_type.toUpperCase() === 'SIP') && (
                <>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Scheme Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.scheme_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">AMC Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.amc_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">SIP Registration No</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.xsip_registration_no)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">SIP Registration Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.xsip_registration_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Amount</Typography>
                    <Typography variant="body1" gutterBottom>{formatCurrency(selectedTransaction.installments_amount)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Frequency</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.frequency_type)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">No. of Installments</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.no_of_installments)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Start Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.start_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">End Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.end_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">NSE Mandate ID</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.nse_mandate_id)}</Typography>
                  </Grid>
                </>
              )}

              {selectedTransaction.registration_type?.toUpperCase() === 'SWP' && (
                <>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Scheme Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.scheme_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">AMC Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.amc_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">SWP Registration No</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.swp_registration_no)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">SWP Registration Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.swp_registration_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Withdrawal Amount</Typography>
                    <Typography variant="body1" gutterBottom>{formatCurrency(selectedTransaction.withdrawl_amount)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Frequency</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.frequency_type)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">No. of Withdrawals</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.no_of_withdrawls)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Start Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.swp_start_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">End Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.swp_end_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Bank Account No</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.bank_account_no)}</Typography>
                  </Grid>
                </>
              )}

              {selectedTransaction.registration_type?.toUpperCase() === 'STP' && (
                <>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">From Scheme Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.from_scheme_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">To Scheme Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.to_scheme_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">From AMC Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.from_amc_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">To AMC Name</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.to_amc_name)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">STP Registration No</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.stp_registration_no)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">STP Registration Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.stp_registration_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Transfer Amount</Typography>
                    <Typography variant="body1" gutterBottom>{formatCurrency(selectedTransaction.transfer_amount)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Transfer Units</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.transfer_units)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Frequency</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.frequency_type || selectedTransaction.trxn_mode)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">No. of Transfers</Typography>
                    <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.no_of_transfers)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">Start Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.stp_start_date)}</Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <Typography variant="subtitle2" color="textSecondary">End Date</Typography>
                    <Typography variant="body1" gutterBottom>{formatDateDisplay(selectedTransaction.stp_end_date)}</Typography>
                  </Grid>
                </>
              )}

              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">EUIN No</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.euin_no)}</Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <Typography variant="subtitle2" color="textSecondary">Entry By</Typography>
                <Typography variant="body1" gutterBottom>{renderTableCell(selectedTransaction.entry_by)}</Typography>
              </Grid>
              <Grid size={12}>
                <Typography variant="subtitle2" color="textSecondary">Status</Typography>
                <Chip
                  label={renderTableCell(selectedTransaction.status)}
                  color={getStatusColor(selectedTransaction.status)}
                  size="small"
                />
              </Grid>
              {selectedTransaction.remarks && selectedTransaction.remarks !== ' ' && (
                <Grid size={12}>
                  <Typography variant="subtitle2" color="textSecondary">Remarks</Typography>
                  <Typography variant="body2" color="textSecondary" sx={{ fontStyle: 'italic' }}>
                    {selectedTransaction.remarks}
                  </Typography>
                </Grid>
              )}
            </Grid>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDialog}>Close</Button>
        </DialogActions>
      </Dialog>
    </Container>
  );
}
