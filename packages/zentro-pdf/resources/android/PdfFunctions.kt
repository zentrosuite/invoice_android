package com.zentrosuite.pdf

import android.annotation.SuppressLint
import android.content.Context
import android.os.Handler
import android.os.Looper
import android.print.PrintAttributes
import android.print.ZentroPdfPrinter
import android.util.Log
import android.view.View
import android.webkit.WebView
import android.webkit.WebViewClient
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import kotlin.math.ceil
import kotlin.math.roundToInt

/**
 * Namespace "ZentroPdf.*"
 *
 * The app's PDFs used to come only from dompdf, inside the phone's PHP. dompdf draws letters one
 * by one: it cannot join Tamil letters or put vowel signs on the correct side, so a Tamil name
 * came out wrong. Android's WebView is Chrome's engine, which shapes Tamil (and every other
 * script) correctly. This prints the invoice's HTML with it, straight to a file, in the
 * background: no print dialog, nothing on screen.
 */
object PdfFunctions {

    private const val TAG = "ZentroPdf"
    private const val MILS_PER_MM = 1000.0 / 25.4
    private const val CSS_PX_PER_MM = 96.0 / 25.4

    /**
     * Parameters:
     *   - htmlPath: string — absolute path of the HTML file (its fonts are file:// URLs)
     *   - pdfPath:  string — absolute path to write the PDF to
     *   - widthMm:  number — page width (default 210)
     *   - heightMm: number — page height (default 297); 0 = one page exactly as long as the content
     *   - marginMm: number — the page margins the HTML's @page asks for, top + bottom, only used
     *               to size a content-length page (default 10)
     * Returns: path, widthMm, heightMm (the page size used), or error.
     */
    class FromHtml(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val htmlPath = parameters["htmlPath"] as? String ?: return mapOf("error" to "htmlPath is required")
            val pdfPath = parameters["pdfPath"] as? String ?: return mapOf("error" to "pdfPath is required")
            val widthMm = (parameters["widthMm"] as? Number)?.toDouble() ?: 210.0
            val heightMm = (parameters["heightMm"] as? Number)?.toDouble() ?: 297.0
            val marginMm = (parameters["marginMm"] as? Number)?.toDouble() ?: 10.0

            if (!File(htmlPath).exists()) return mapOf("error" to "HTML file not found")
            // The bridge runs on PHP's thread; the WebView must run on the main thread. Waiting
            // here on the main thread itself would never finish.
            if (Looper.myLooper() == Looper.getMainLooper()) return mapOf("error" to "Called on the main thread")

            val latch = CountDownLatch(1)
            var result: Map<String, Any> = mapOf("error" to "The PDF could not be made")
            Handler(Looper.getMainLooper()).post {
                try {
                    render(htmlPath, File(pdfPath), widthMm, heightMm, marginMm) { r ->
                        result = r
                        latch.countDown()
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "render failed: ${e.message}", e)
                    result = mapOf("error" to (e.message ?: "The PDF could not be made"))
                    latch.countDown()
                }
            }
            if (!latch.await(60, TimeUnit.SECONDS)) return mapOf("error" to "Making the PDF took too long")
            return result
        }

        @SuppressLint("SetJavaScriptEnabled")
        private fun render(htmlPath: String, out: File, widthMm: Double, heightMm: Double, marginMm: Double, done: (Map<String, Any>) -> Unit) {
            out.parentFile?.mkdirs()
            val webView = WebView(context)
            webView.settings.javaScriptEnabled = true // only to measure a receipt's length
            @Suppress("DEPRECATION")
            webView.settings.allowFileAccess = true
            @Suppress("DEPRECATION")
            webView.settings.allowFileAccessFromFileURLs = true
            webView.settings.blockNetworkLoads = true

            // Laid out at the page's content width, so a measured length matches the printed one.
            val density = context.resources.displayMetrics.density
            val contentWidthMm = if (heightMm > 0) widthMm else widthMm - marginMm
            val widthPx = (contentWidthMm * CSS_PX_PER_MM * density).roundToInt()
            val heightPx = (100 * density).roundToInt()
            webView.measure(
                View.MeasureSpec.makeMeasureSpec(widthPx, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(heightPx, View.MeasureSpec.EXACTLY),
            )
            webView.layout(0, 0, widthPx, heightPx)

            var finished = false
            fun finish(r: Map<String, Any>) {
                if (finished) return
                finished = true
                done(r)
                webView.destroy()
            }

            webView.webViewClient = object : WebViewClient() {
                override fun onPageFinished(view: WebView, url: String) {
                    if (finished) return
                    // Fonts arrive after the page. Once they are in, the page's length is put
                    // where it can be read back (evaluateJavascript cannot wait for a promise).
                    view.evaluateJavascript(
                        "(document.fonts ? document.fonts.ready : Promise.resolve()).then(function () {" +
                            "window.__zentroHeight = Math.ceil(document.body.getBoundingClientRect().bottom); });",
                        null,
                    )
                    poll(view, 0)
                }

                fun poll(view: WebView, attempt: Int) {
                    Handler(Looper.getMainLooper()).postDelayed({
                        view.evaluateJavascript("window.__zentroHeight || 0") { value ->
                            val contentPx = value?.trim('"')?.toDoubleOrNull() ?: 0.0
                            if (contentPx <= 0 && attempt < 30) {
                                poll(view, attempt + 1)
                                return@evaluateJavascript
                            }
                            val pageHeightMm = if (heightMm > 0) heightMm else ceil(contentPx / CSS_PX_PER_MM + marginMm + 2.0)
                            print(view, out, widthMm, pageHeightMm, ::finish)
                        }
                    }, 100)
                }
            }
            webView.loadUrl("file://$htmlPath")
        }

        private fun print(view: WebView, out: File, widthMm: Double, heightMm: Double, finish: (Map<String, Any>) -> Unit) {
            val size = PrintAttributes.MediaSize(
                "zentro_${widthMm.roundToInt()}x${heightMm.roundToInt()}",
                "Invoice",
                (widthMm * MILS_PER_MM).roundToInt(),
                (heightMm * MILS_PER_MM).roundToInt(),
            )
            val attributes = PrintAttributes.Builder()
                .setMediaSize(size)
                .setResolution(PrintAttributes.Resolution("pdf", "pdf", 600, 600))
                .setMinMargins(PrintAttributes.Margins.NO_MARGINS)
                .build()
            val adapter = view.createPrintDocumentAdapter("invoice")
            ZentroPdfPrinter(attributes).print(adapter, out) { error ->
                if (error == null && out.exists() && out.length() > 0) {
                    Log.d(TAG, "Wrote ${out.length()} bytes, ${widthMm}x${heightMm} mm")
                    finish(mapOf("path" to out.absolutePath, "widthMm" to widthMm, "heightMm" to heightMm))
                } else {
                    Log.e(TAG, "print failed: $error")
                    finish(mapOf("error" to (error ?: "The PDF came out empty")))
                }
            }
        }
    }
}
