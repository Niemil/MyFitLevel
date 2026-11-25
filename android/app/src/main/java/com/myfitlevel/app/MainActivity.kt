package com.myfitlevel.app

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {
    private lateinit var web: WebView
    private var chooser: ValueCallback<Array<Uri>>? = null

    private val pick = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { r ->
        val data = r.data
        val uris: Array<Uri>? = when {
            r.resultCode != RESULT_OK || data == null -> null
            data.clipData != null -> Array(data.clipData!!.itemCount) { i -> data.clipData!!.getItemAt(i).uri }
            data.data != null -> arrayOf(data.data!!)
            else -> null
        }
        chooser?.onReceiveValue(uris)
        chooser = null
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        web = WebView(this)
        setContentView(web)
        web.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            cacheMode = WebSettings.LOAD_DEFAULT
            mediaPlaybackRequiresUserGesture = false
            userAgentString = userAgentString + " MyFitLevelApp/1.0"
        }
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setCookie(Prefs.SITE, "mflapp=1; Max-Age=31536000; Path=/; Secure; SameSite=Lax")
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false)
        web.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView, url: String?) {
                super.onPageFinished(view, url)
                val path = Uri.parse(url ?: "").path ?: ""
                if (path.startsWith("/feed") || path.startsWith("/welcome") || path.startsWith("/profile")) offerSteps()
            }

            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val u = request.url
                if (u.scheme == "mfl") {
                    if (u.host == "key") {
                        val tok = u.lastPathSegment ?: ""
                        if (tok.length >= 20) Prefs.setKey(this@MainActivity, tok)
                        startActivity(Intent(this@MainActivity, SyncActivity::class.java).putExtra("auto", true))
                    } else if (u.host == "widgets") {
                        startActivity(Intent(this@MainActivity, WidgetsActivity::class.java))
                    } else {
                        startActivity(Intent(this@MainActivity, SyncActivity::class.java))
                    }
                    return true
                }
                if (u.host == Uri.parse(Prefs.SITE).host) return false
                startActivity(Intent(Intent.ACTION_VIEW, u))
                return true
            }
        }
        web.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(w: WebView, cb: ValueCallback<Array<Uri>>, params: FileChooserParams): Boolean {
                chooser?.onReceiveValue(null)
                chooser = cb
                return try {
                    pick.launch(params.createIntent().apply { putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true) })
                    true
                } catch (e: Exception) {
                    chooser = null
                    false
                }
            }
        }
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (web.canGoBack()) web.goBack() else finish()
            }
        })
        SyncWorker.schedule(this)
        Updater.check(this, true)
        if (savedInstanceState == null) web.loadUrl(intent.getStringExtra("url") ?: (Prefs.SITE + "/"))
        else web.restoreState(savedInstanceState)
    }

    private var offering = false


    /** Once after login: suggest linking this phone / allowing Health Connect. Never nags after you answer. */
    private fun offerSteps() {
        if (offering || isFinishing) return
        if (Prefs.key(this).isEmpty()) {
            if (Prefs.asked(this, "connect")) return
            offering = true
            Prefs.setAsked(this, "connect")
            android.app.AlertDialog.Builder(this)
                .setTitle("Sync your steps?")
                .setMessage("Connect this phone to Health Connect and your daily steps are added automatically. Steps give points too. It is optional, and you can do it later on the Steps page.")
                .setPositiveButton("Connect") { _, _ -> web.loadUrl(Prefs.SITE + "/steps?connect=1") }
                .setNegativeButton("Not now", null)
                .setOnDismissListener { offering = false }
                .show()
            return
        }
        if (Prefs.asked(this, "perm")) return
        offering = true
        lifecycleScope.launch {
            try {
                if (androidx.health.connect.client.HealthConnectClient.getSdkStatus(this@MainActivity) != androidx.health.connect.client.HealthConnectClient.SDK_AVAILABLE) return@launch
                val granted = androidx.health.connect.client.HealthConnectClient.getOrCreate(this@MainActivity).permissionController.getGrantedPermissions()
                if (StepSync.stepsPerm in granted) { Prefs.setAsked(this@MainActivity, "perm"); return@launch }
                Prefs.setAsked(this@MainActivity, "perm")
                android.app.AlertDialog.Builder(this@MainActivity)
                    .setTitle("Allow step access?")
                    .setMessage("This phone is linked, but Health Connect has not been allowed to share your steps yet. Allow it to sync steps automatically.")
                    .setPositiveButton("Allow") { _, _ -> startActivity(Intent(this@MainActivity, SyncActivity::class.java).putExtra("auto", true)) }
                    .setNegativeButton("Not now", null)
                    .show()
            } catch (e: Exception) {
            } finally {
                offering = false
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        intent.getStringExtra("url")?.let { web.loadUrl(it) }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        web.saveState(outState)
    }

    override fun onResume() {
        super.onResume()
        SyncWorker.syncSoon(this)
        Updater.check(this)
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }
}
