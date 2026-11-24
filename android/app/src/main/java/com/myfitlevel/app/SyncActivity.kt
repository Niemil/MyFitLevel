package com.myfitlevel.app

import android.os.Bundle
import android.text.InputType
import android.view.Gravity
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.health.connect.client.PermissionController
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

class SyncActivity : AppCompatActivity() {
    private lateinit var status: TextView
    private lateinit var keyField: EditText

    private val askPerms = registerForActivityResult(PermissionController.createRequestPermissionResultContract()) {
        syncNow()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val pad = (16 * resources.displayMetrics.density).toInt()
        val col = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(pad, pad * 2, pad, pad) }
        fun label(t: String, size: Float = 15f) = TextView(this).apply { text = t; textSize = size; setTextColor(0xFFE6EAF0.toInt()); setPadding(0, pad / 2, 0, pad / 2) }

        col.addView(label("Step sync", 24f))
        col.addView(label("Sends your daily steps from Health Connect to MyFitLevel, so they count for your team quests and give a few points."))
        col.addView(label("1. Create an API key on the site under Settings > API access, copy it and paste it here:"))
        keyField = EditText(this).apply {
            setText(Prefs.key(this@SyncActivity)); hint = "API key"; inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
            setTextColor(0xFFFFFFFF.toInt()); setHintTextColor(0xFF8893A3.toInt())
        }
        col.addView(keyField)
        col.addView(label("2. Allow reading steps in Health Connect, then sync:"))
        col.addView(Button(this).apply {
            text = "Save key and allow Health Connect"
            setOnClickListener {
                Prefs.setKey(this@SyncActivity, keyField.text.toString())
                askPerms.launch(StepSync.toRequest)
            }
        })
        col.addView(Button(this).apply {
            text = "Sync now"
            setOnClickListener { Prefs.setKey(this@SyncActivity, keyField.text.toString()); syncNow() }
        })
        status = label(Prefs.status(this)).apply { gravity = Gravity.START }
        col.addView(status)
        col.addView(label("The app also syncs by itself about once an hour. If you forget a day, you can correct it by hand on the Steps page.", 13f))
        setContentView(ScrollView(this).apply { addView(col) })
        SyncWorker.schedule(this)
    }

    private fun syncNow() {
        status.text = "Syncing..."
        lifecycleScope.launch {
            val s = StepSync.sync(this@SyncActivity)
            Prefs.setStatus(this@SyncActivity, s)
            status.text = s
        }
    }
}
