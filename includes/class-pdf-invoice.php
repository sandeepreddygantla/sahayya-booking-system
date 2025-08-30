<?php

if (!defined('ABSPATH')) {
    exit;
}

// Include TCPDF library
require_once plugin_dir_path(__FILE__) . '../lib/TCPDF/tcpdf.php';

class Sahayya_PDF_Invoice extends TCPDF {
    
    private $company_info;
    private $invoice_data;
    private $booking_data;
    private $customer_data;
    
    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        parent::__construct($orientation, $unit, $format);
        
        // Set company information
        $this->company_info = array(
            'name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'address' => get_option('sahayya_company_address', 'Your Business Address'),
            'city' => get_option('sahayya_company_city', 'Your City'),
            'phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'email' => get_option('sahayya_company_email', get_option('admin_email')),
            'website' => get_option('sahayya_company_website', get_site_url()),
            'logo' => get_option('sahayya_company_logo', ''),
            'tax_number' => get_option('sahayya_tax_number', ''),
            'registration_number' => get_option('sahayya_registration_number', '')
        );
        
        // Set document information
        $this->SetCreator('Sahayya Booking System');
        $this->SetAuthor($this->company_info['name']);
        $this->SetTitle('Invoice');
        $this->SetSubject('Service Invoice');
        $this->SetKeywords('Invoice, Service, Booking');
        
        // Set default settings
        $this->SetMargins(20, 30, 20);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(TRUE, 25);
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Set font
        $this->SetFont('freesans', '', 10);
    }
    
    public function Header() {
        // Company logo
        if (!empty($this->company_info['logo']) && file_exists($this->company_info['logo'])) {
            $this->Image($this->company_info['logo'], 20, 10, 30, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $logo_width = 35;
        } else {
            $logo_width = 0;
        }
        
        // Invoice title - positioned at far right
        $this->SetFont('freesansb', 'B', 20);
        $this->SetTextColor(41, 128, 185); // Professional blue
        $this->SetXY(160, 15);
        $this->Cell(30, 12, 'INVOICE', 0, 1, 'R');
        
        // Company information - positioned with adequate width but limited to prevent overlap
        $this->SetTextColor(0, 0, 0); // Reset to black
        $this->SetFont('freesansb', 'B', 13);
        $this->SetXY(20 + $logo_width, 15);
        $this->Cell(80, 6, $this->company_info['name'], 0, 1, 'L'); // Increased width to 80
        
        $this->SetFont('freesans', '', 9);
        $this->SetX(20 + $logo_width);
        $this->Cell(80, 4, $this->company_info['address'], 0, 1, 'L');
        $this->SetX(20 + $logo_width);
        $this->Cell(80, 4, $this->company_info['city'], 0, 1, 'L');
        $this->SetX(20 + $logo_width);
        $this->Cell(80, 4, $this->company_info['phone'], 0, 1, 'L'); // Full phone number
        $this->SetX(20 + $logo_width);
        $this->Cell(80, 4, $this->company_info['email'], 0, 1, 'L');
        
        // Add some space after header
        $this->Ln(15);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('freesans', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Page number
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 1, 'C');
        
        // Footer text
        $this->Cell(0, 5, 'Thank you for choosing ' . $this->company_info['name'], 0, 1, 'C');
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }
    
    public function generate_invoice($invoice_id) {
        // Get invoice data
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }
        
        // Get related data
        $booking = Sahayya_Booking_Database::get_booking($invoice->booking_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $customer = get_userdata($invoice->customer_id);
        $invoice_items = Sahayya_Booking_Database::get_invoice_items($invoice_id);
        
        // Get dependents data
        $dependent_ids = json_decode($booking->dependent_ids, true);
        $dependents = array();
        if (!empty($dependent_ids)) {
            foreach ($dependent_ids as $dep_id) {
                $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                if ($dep) $dependents[] = $dep;
            }
        }
        
        $this->invoice_data = $invoice;
        $this->booking_data = $booking;
        $this->customer_data = $customer;
        
        // Add page
        $this->AddPage();
        
        // Invoice details section
        $this->add_invoice_details();
        
        // Customer information
        $this->add_customer_info();
        
        // Service details
        $this->add_service_details($service, $dependents);
        
        // Invoice items table
        $this->add_invoice_items($invoice_items);
        
        // Payment information
        $this->add_payment_info();
        
        // Terms and notes
        $this->add_terms_and_notes();
        
        return $this;
    }
    
    private function add_invoice_details() {
        $y_start = $this->GetY();
        
        // Invoice details box
        $this->SetFillColor(245, 245, 245);
        $this->Rect(20, $y_start, 85, 35, 'F');
        
        $this->SetFont('freesansb', 'B', 11);
        $this->SetXY(25, $y_start + 5);
        $this->Cell(75, 6, 'Invoice Details', 0, 1, 'L');
        
        $this->SetFont('freesans', '', 10);
        $this->SetXY(25, $y_start + 12);
        $this->Cell(35, 5, 'Invoice Number:', 0, 0, 'L');
        $this->Cell(40, 5, $this->invoice_data->invoice_number, 0, 1, 'L');
        
        $this->SetXY(25, $y_start + 17);
        $this->Cell(35, 5, 'Issue Date:', 0, 0, 'L');
        $this->Cell(40, 5, date('d M Y', strtotime($this->invoice_data->issue_date)), 0, 1, 'L');
        
        $this->SetXY(25, $y_start + 22);
        $this->Cell(35, 5, 'Due Date:', 0, 0, 'L');
        $this->Cell(40, 5, date('d M Y', strtotime($this->invoice_data->due_date)), 0, 1, 'L');
        
        $this->SetXY(25, $y_start + 27);
        $this->Cell(35, 5, 'Status:', 0, 0, 'L');
        $status_color = $this->invoice_data->status == 'paid' ? array(39, 174, 96) : array(231, 76, 60);
        $this->SetTextColor($status_color[0], $status_color[1], $status_color[2]);
        $this->Cell(40, 5, strtoupper($this->invoice_data->status), 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);
        
        // Booking details box
        $this->SetFillColor(241, 248, 255);
        $this->Rect(110, $y_start, 80, 35, 'F');
        
        $this->SetFont('freesansb', 'B', 11);
        $this->SetXY(115, $y_start + 5);
        $this->Cell(70, 6, 'Booking Details', 0, 1, 'L');
        
        $this->SetFont('freesans', '', 10);
        $this->SetXY(115, $y_start + 12);
        $this->Cell(35, 5, 'Booking ID:', 0, 0, 'L');
        $this->Cell(35, 5, $this->booking_data->booking_number, 0, 1, 'L');
        
        $this->SetXY(115, $y_start + 17);
        $this->Cell(35, 5, 'Service Date:', 0, 0, 'L');
        $this->Cell(35, 5, date('d M Y', strtotime($this->booking_data->booking_date)), 0, 1, 'L');
        
        $this->SetXY(115, $y_start + 22);
        $this->Cell(35, 5, 'Service Time:', 0, 0, 'L');
        $this->Cell(35, 5, date('H:i', strtotime($this->booking_data->booking_time)), 0, 1, 'L');
        
        $this->SetXY(115, $y_start + 27);
        $this->Cell(35, 5, 'Urgency:', 0, 0, 'L');
        $this->Cell(35, 5, ucfirst($this->booking_data->urgency_level), 0, 1, 'L');
        
        $this->SetY($y_start + 40);
    }
    
    private function add_customer_info() {
        $this->Ln(5);
        
        $this->SetFont('freesansb', 'B', 12);
        $this->Cell(0, 8, 'Bill To:', 0, 1, 'L');
        
        $this->SetFont('freesansb', 'B', 11);
        $this->Cell(0, 6, $this->customer_data->display_name, 0, 1, 'L');
        
        $this->SetFont('freesans', '', 10);
        $this->Cell(0, 5, 'Email: ' . $this->customer_data->user_email, 0, 1, 'L');
        
        // Get customer phone from user meta
        $phone = get_user_meta($this->customer_data->ID, 'phone', true);
        if ($phone) {
            $this->Cell(0, 5, 'Phone: ' . $phone, 0, 1, 'L');
        }
        
        $this->Ln(5);
    }
    
    private function add_service_details($service, $dependents) {
        $this->SetFont('freesansb', 'B', 12);
        $this->Cell(0, 8, 'Service Information:', 0, 1, 'L');
        
        // Service details box
        $this->SetFillColor(250, 250, 250);
        $this->Rect(20, $this->GetY(), 170, 25, 'F');
        
        $y_start = $this->GetY();
        
        $this->SetFont('freesansb', 'B', 11);
        $this->SetXY(25, $y_start + 5);
        $this->Cell(0, 6, $service->name, 0, 1, 'L');
        
        $this->SetFont('freesans', '', 10);
        $this->SetXY(25, $y_start + 12);
        $this->MultiCell(140, 5, $service->description, 0, 'L');
        
        $this->SetY($y_start + 25);
        
        // Dependents information
        if (!empty($dependents)) {
            $this->Ln(5);
            $this->SetFont('freesansb', 'B', 11);
            $this->Cell(0, 6, 'Selected Dependents:', 0, 1, 'L');
            
            $this->SetFont('freesans', '', 10);
            foreach ($dependents as $dependent) {
                $this->Cell(0, 5, '• ' . $dependent->name . ' (' . $dependent->age . ' years, ' . ucfirst($dependent->gender) . ')', 0, 1, 'L');
            }
        }
        
        // Special instructions
        if (!empty($this->booking_data->special_instructions)) {
            $this->Ln(3);
            $this->SetFont('freesansb', 'B', 10);
            $this->Cell(0, 5, 'Special Instructions:', 0, 1, 'L');
            $this->SetFont('freesans', '', 9);
            $this->MultiCell(0, 4, $this->booking_data->special_instructions, 0, 'L');
        }
        
        $this->Ln(10);
    }
    
    private function add_invoice_items($items) {
        $this->SetFont('freesansb', 'B', 11);
        $this->Cell(0, 8, 'Invoice Items:', 0, 1, 'L');
        
        // Table header
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('freesansb', 'B', 10);
        
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(80, 8, 'Description', 1, 0, 'L', true);
        $this->Cell(20, 8, 'Quantity', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Unit Price', 1, 0, 'R', true);
        $this->Cell(35, 8, 'Total Price', 1, 1, 'R', true);
        
        // Table content
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('freesans', '', 9);
        
        $row_count = 1;
        foreach ($items as $item) {
            $fill = ($row_count % 2 == 0);
            $this->SetFillColor(248, 249, 250);
            
            $this->Cell(10, 6, $row_count, 1, 0, 'C', $fill);
            $this->Cell(80, 6, $item->description, 1, 0, 'L', $fill);
            $this->Cell(20, 6, number_format($item->quantity, 1), 1, 0, 'C', $fill);
            $this->Cell(25, 6, '₹' . number_format($item->unit_price, 2), 1, 0, 'R', $fill);
            $this->Cell(35, 6, '₹' . number_format($item->total_price, 2), 1, 1, 'R', $fill);
            
            $row_count++;
        }
        
        // Totals section
        $this->Ln(5);
        
        // Subtotal
        $this->SetFont('freesans', '', 10);
        $this->Cell(135, 6, 'Subtotal:', 0, 0, 'R');
        $this->Cell(35, 6, '₹' . number_format($this->invoice_data->subtotal, 2), 1, 1, 'R');
        
        // Tax
        if ($this->invoice_data->tax_amount > 0) {
            $this->Cell(135, 6, 'Tax (' . number_format($this->invoice_data->tax_rate, 1) . '%):', 0, 0, 'R');
            $this->Cell(35, 6, '₹' . number_format($this->invoice_data->tax_amount, 2), 1, 1, 'R');
        }
        
        // Discount
        if ($this->invoice_data->discount_amount > 0) {
            $this->SetTextColor(39, 174, 96);
            $this->Cell(135, 6, 'Discount:', 0, 0, 'R');
            $this->Cell(35, 6, '-₹' . number_format($this->invoice_data->discount_amount, 2), 1, 1, 'R');
            $this->SetTextColor(0, 0, 0);
        }
        
        // Total
        $this->SetFont('freesansb', 'B', 12);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(135, 8, 'TOTAL AMOUNT:', 0, 0, 'R');
        $this->Cell(35, 8, '₹' . number_format($this->invoice_data->total_amount, 2), 1, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
    }
    
    private function add_payment_info() {
        $this->SetFont('freesansb', 'B', 11);
        $this->Cell(0, 8, 'Payment Information:', 0, 1, 'L');
        
        $this->SetFont('freesans', '', 10);
        $this->Cell(40, 5, 'Amount Paid:', 0, 0, 'L');
        $this->Cell(50, 5, '₹' . number_format($this->invoice_data->paid_amount, 2), 0, 1, 'L');
        
        $this->Cell(40, 5, 'Balance Due:', 0, 0, 'L');
        $balance_color = $this->invoice_data->balance_amount > 0 ? array(231, 76, 60) : array(39, 174, 96);
        $this->SetTextColor($balance_color[0], $balance_color[1], $balance_color[2]);
        $this->Cell(50, 5, '₹' . number_format($this->invoice_data->balance_amount, 2), 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);
        
        if (!empty($this->invoice_data->payment_terms)) {
            $this->Ln(3);
            $this->SetFont('freesansb', 'B', 10);
            $this->Cell(0, 5, 'Payment Terms:', 0, 1, 'L');
            $this->SetFont('freesans', '', 9);
            $this->MultiCell(0, 4, $this->invoice_data->payment_terms, 0, 'L');
        }
        
        $this->Ln(5);
    }
    
    private function add_terms_and_notes() {
        if (!empty($this->invoice_data->notes)) {
            $this->SetFont('freesansb', 'B', 11);
            $this->Cell(0, 8, 'Notes:', 0, 1, 'L');
            
            $this->SetFont('freesans', '', 10);
            $this->MultiCell(0, 5, $this->invoice_data->notes, 0, 'L');
            $this->Ln(5);
        }
        
        // Terms and conditions
        $terms = get_option('sahayya_invoice_terms', 'Thank you for your business! Please remit payment within 30 days.');
        
        $this->SetFont('freesansb', 'B', 11);
        $this->Cell(0, 8, 'Terms & Conditions:', 0, 1, 'L');
        
        $this->SetFont('freesans', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->MultiCell(0, 4, $terms, 0, 'L');
        
        $this->SetTextColor(0, 0, 0);
    }
    
    public static function download_invoice_pdf($invoice_id, $download = true) {
        try {
            $pdf = new self();
            $pdf->generate_invoice($invoice_id);
            
            $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
            $filename = 'invoice-' . $invoice->invoice_number . '.pdf';
            
            if ($download) {
                $pdf->Output($filename, 'D');
            } else {
                return $pdf->Output($filename, 'S');
            }
            
        } catch (Exception $e) {
            wp_die('Error generating PDF: ' . $e->getMessage());
        }
    }
    
    public static function save_invoice_pdf($invoice_id, $path = null) {
        try {
            $pdf = new self();
            $pdf->generate_invoice($invoice_id);
            
            $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
            
            if (!$path) {
                $upload_dir = wp_upload_dir();
                $sahayya_dir = $upload_dir['basedir'] . '/sahayya-invoices';
                
                if (!file_exists($sahayya_dir)) {
                    wp_mkdir_p($sahayya_dir);
                }
                
                $path = $sahayya_dir . '/invoice-' . $invoice->invoice_number . '.pdf';
            }
            
            $pdf->Output($path, 'F');
            return $path;
            
        } catch (Exception $e) {
            error_log('Error saving PDF: ' . $e->getMessage());
            return false;
        }
    }
}