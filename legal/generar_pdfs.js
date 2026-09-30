// Genera los PDFs legales a partir de los HTML de esta carpeta.
// Uso (desde la raíz del proyecto): node legal/generar_pdfs.js
const path = require('path');
let chromium;
try { ({ chromium } = require('playwright')); } catch { ({ chromium } = require('/opt/node22/lib/node_modules/playwright')); }

const documentos = [
    ['terminos.html', 'Terminos_y_Condiciones_Cliente_Cleania.pdf'],
    ['aviso_privacidad.html', 'Aviso_de_Privacidad_Cleania.pdf'],
];

(async () => {
    const navegador = await chromium.launch();
    const pagina = await navegador.newPage();
    for (const [origen, destino] of documentos) {
        await pagina.goto('file://' + path.join(__dirname, origen));
        await pagina.pdf({
            path: path.join(__dirname, '..', destino),
            format: 'A4',
            preferCSSPageSize: true,
            printBackground: true,
            displayHeaderFooter: true,
            headerTemplate: '<span></span>',
            footerTemplate: '<div style="width:100%;font-size:8pt;color:#64748b;text-align:right;padding-right:22mm;font-family:Arial,sans-serif">Página <span class="pageNumber"></span> de <span class="totalPages"></span></div>',
        });
        console.log('Generado:', destino);
    }
    await navegador.close();
})();
