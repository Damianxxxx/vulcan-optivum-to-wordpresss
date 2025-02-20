function printTable() {
  var tabela = document.querySelector('.tabela').cloneNode(true);
  var headerText = document.querySelector('h2').innerText;

  // Tworzymy iframe do drukowania
  var printFrame = document.createElement('iframe');
  printFrame.style.position = 'absolute';
  printFrame.style.width = '0';
  printFrame.style.height = '0';
  printFrame.style.border = 'none';
  document.body.appendChild(printFrame);

  var printDocument = printFrame.contentWindow.document;
  printDocument.open();
  printDocument.write(`
    <html>
      <head>
        <title>Print Table</title>
        <style type="text/css">
          @media print {
            body {
              text-align: center;
              margin: 0;
              padding: 0;
              font-family: Arial, sans-serif;
            }
            .print-container {
              text-align: center;
              margin: 0;
              padding: 0;
              width: 100%;
            }
            .tabela {
              margin: 0 auto;
              width: auto;
              display: table;
	      border: none;
            }
            .print-date {
              margin-top: 5px;
              text-align: center;
            }
            h2 {
              color: #2B6CB0;
	      font-size: 2rem;
            }
            a {
              text-decoration: none; /* Usunięcie podkreślenia z linków */
            }
            th, td {
              border: 1px solid #ddd; /* Opcjonalnie, dodaj granice */
              padding: 8px; /* Opcjonalnie, dodaj odstęp wewnętrzny */
            }
            /* Kolor tekstu w pierwszej kolumnie */
            td:first-child, th:first-child {
              color: #00006e;
            }
            tr:first-child td {
              color: #00006e;
            }
            @page {
              margin: 20mm;
            }
          }
        </style>
      </head>
      <body>
        <div class="print-container">
          <div class="center-text"><h2>${headerText}</h2></div>
          <div class="print-date" style="margin-top: 5px;">
          </div>
          <div class="tabela">${tabela.innerHTML}</div>
        </div>
      </body>
    </html>
  `);
  printDocument.close();

  // Uruchomienie drukowania
  printFrame.contentWindow.focus();
  printFrame.contentWindow.print();

  // Usunięcie iframe po zakończeniu drukowania
  printFrame.addEventListener('afterprint', function() {
    document.body.removeChild(printFrame);
  });
}
