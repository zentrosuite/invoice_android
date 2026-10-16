// In android.print on purpose: PrintDocumentAdapter's LayoutResultCallback and WriteResultCallback
// can only be subclassed from this package. This is the usual way to save a WebView's print
// output straight to a file, without the system print dialog.
package android.print

import android.os.CancellationSignal
import android.os.ParcelFileDescriptor
import java.io.File

class ZentroPdfPrinter(private val attributes: PrintAttributes) {

    fun print(adapter: PrintDocumentAdapter, file: File, done: (String?) -> Unit) {
        adapter.onLayout(null, attributes, null, object : PrintDocumentAdapter.LayoutResultCallback() {
            override fun onLayoutFinished(info: PrintDocumentInfo?, changed: Boolean) {
                val descriptor = try {
                    ParcelFileDescriptor.open(
                        file,
                        ParcelFileDescriptor.MODE_CREATE or ParcelFileDescriptor.MODE_TRUNCATE or ParcelFileDescriptor.MODE_READ_WRITE,
                    )
                } catch (e: Exception) {
                    done(e.message ?: "Could not create the PDF file")
                    return
                }
                adapter.onWrite(arrayOf(PageRange.ALL_PAGES), descriptor, CancellationSignal(), object : PrintDocumentAdapter.WriteResultCallback() {
                    override fun onWriteFinished(pages: Array<PageRange>) {
                        descriptor.close()
                        done(null)
                    }

                    override fun onWriteFailed(error: CharSequence?) {
                        descriptor.close()
                        done(error?.toString() ?: "The PDF could not be written")
                    }
                })
            }

            override fun onLayoutFailed(error: CharSequence?) {
                done(error?.toString() ?: "The page could not be laid out")
            }
        }, null)
    }
}
