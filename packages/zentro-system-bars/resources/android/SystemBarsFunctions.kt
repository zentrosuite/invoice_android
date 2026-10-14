package com.zentrosuite.systembars

import android.util.Log
import androidx.core.view.WindowInsetsControllerCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Namespace "ZentroSystemBars.*"
 *
 * NativePHP colours the status bar icons from the PHONE's dark mode. The app has its own
 * light/dark switch, so a phone in dark mode showing the app in light mode got white icons on
 * a white bar — the clock, signal and battery vanished. The page reports its theme instead.
 */
object SystemBarsFunctions {

    private const val TAG = "ZentroSystemBars"

    /**
     * Parameters:
     *   - dark: boolean — the page is dark (use light icons) or light (use dark icons)
     */
    class SetStyle(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val dark = when (val value = parameters["dark"]) {
                is Boolean -> value
                is String -> value == "true" || value == "1"
                is Number -> value.toInt() != 0
                else -> false
            }

            activity.runOnUiThread {
                val controller = WindowInsetsControllerCompat(activity.window, activity.window.decorView)
                // "Light" bars mean dark icons, for a light page.
                controller.isAppearanceLightStatusBars = !dark
                controller.isAppearanceLightNavigationBars = !dark
                Log.d(TAG, "System bar icons for a ${if (dark) "dark" else "light"} page")
            }

            return mapOf("dark" to dark)
        }
    }
}
