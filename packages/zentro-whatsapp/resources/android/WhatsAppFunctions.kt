package com.zentrosuite.whatsapp

import android.content.ActivityNotFoundException
import android.content.ClipData
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.content.FileProvider
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File

/**
 * Namespace "ZentroWhatsApp.*"
 *
 * A web page can open a WhatsApp chat (wa.me) OR hand a file to the share sheet, never both.
 * An app can: an ACTION_SEND aimed at the WhatsApp package with a "jid" extra opens that one
 * chat with the file already attached, ready to send. The "jid" extra is not documented by
 * WhatsApp, but it is what WhatsApp itself reads; if it stops working, WhatsApp just shows its
 * own contact picker with the file — and if no WhatsApp is installed, the system share sheet.
 */
object WhatsAppFunctions {

    private const val TAG = "ZentroWhatsApp"

    /**
     * Parameters:
     *   - filePath: string — absolute path of the file to send (inside the app's storage)
     *   - phone:    string — international number, digits only, e.g. 919688527308 (optional)
     *   - text:     string — caption sent with the file (optional)
     *   - app:      "business" | "personal" — which WhatsApp to try first (default business)
     * Returns: opened = "com.whatsapp.w4b" | "com.whatsapp" | "chooser", or error.
     */
    class SendFile(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val filePath = parameters["filePath"] as? String ?: return mapOf("error" to "filePath is required")
            val phone = (parameters["phone"] as? String ?: "").filter { it.isDigit() }
            val text = parameters["text"] as? String ?: ""
            val preferPersonal = (parameters["app"] as? String) == "personal"

            val source = File(filePath)
            if (!source.exists()) return mapOf("error" to "File not found")

            return try {
                // The FileProvider in this app only exposes the cache folder, so the file is
                // copied there first (the same approach as the official share plugin).
                val shareDir = File(context.cacheDir, "share").apply { mkdirs() }
                val copy = File(shareDir, source.name)
                source.inputStream().use { input -> copy.outputStream().use { input.copyTo(it) } }

                val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", copy)
                val mime = if (copy.extension.equals("pdf", true)) "application/pdf" else "*/*"

                fun intentFor(pkg: String?): Intent = Intent(Intent.ACTION_SEND).apply {
                    type = mime
                    putExtra(Intent.EXTRA_STREAM, uri)
                    if (text.isNotEmpty()) putExtra(Intent.EXTRA_TEXT, text)
                    if (pkg != null && phone.isNotEmpty()) putExtra("jid", "$phone@s.whatsapp.net")
                    if (pkg != null) setPackage(pkg)
                    clipData = ClipData.newRawUri("", uri)
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
                }

                val order = if (preferPersonal) listOf("com.whatsapp", "com.whatsapp.w4b")
                            else listOf("com.whatsapp.w4b", "com.whatsapp")

                for (pkg in order) {
                    try {
                        context.startActivity(intentFor(pkg))
                        Log.d(TAG, "Opened $pkg for $phone")
                        return mapOf("opened" to pkg)
                    } catch (e: ActivityNotFoundException) {
                        Log.d(TAG, "$pkg not installed")
                    }
                }

                // No WhatsApp at all: the ordinary share sheet, file attached.
                val chooser = Intent.createChooser(intentFor(null), "Send invoice")
                chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                context.startActivity(chooser)
                mapOf("opened" to "chooser")
            } catch (e: Exception) {
                Log.e(TAG, "SendFile failed: ${e.message}", e)
                mapOf("error" to (e.message ?: "Could not open WhatsApp"))
            }
        }
    }
}
