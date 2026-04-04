{!! '<?xml version="1.0"?>' !!}
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">
    <Worksheet ss:Name="{{ substr($wallet_type['label'], 0, 31) }}">
        <Table>
            <Row>
                <Cell><Data ss:Type="String">Wallet Category</Data></Cell>
                <Cell><Data ss:Type="String">Branch</Data></Cell>
                <Cell><Data ss:Type="String">Customer Name</Data></Cell>
                <Cell><Data ss:Type="String">Email</Data></Cell>
                <Cell><Data ss:Type="String">Mobile</Data></Cell>
                <Cell><Data ss:Type="String">Account Number</Data></Cell>
                <Cell><Data ss:Type="Number">Current Balance</Data></Cell>
                <Cell><Data ss:Type="String">Locked</Data></Cell>
                <Cell><Data ss:Type="String">Created At</Data></Cell>
            </Row>
            @foreach($rows as $row)
                <Row>
                    <Cell><Data ss:Type="String">{{ $wallet_type['label'] }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['branch_name'] ?: data_get($branch, 'name', 'All branches') }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['customer_name'] }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['email'] }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['mobile'] }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['account_number'] }}</Data></Cell>
                    <Cell><Data ss:Type="Number">{{ number_format((float) $row['current_balance'], 2, '.', '') }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['locked'] ? 'Yes' : 'No' }}</Data></Cell>
                    <Cell><Data ss:Type="String">{{ $row['created_at'] }}</Data></Cell>
                </Row>
            @endforeach
        </Table>
    </Worksheet>
</Workbook>
