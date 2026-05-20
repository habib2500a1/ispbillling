package com.ispplatform.app.ui.common

import android.widget.Toast
import androidx.fragment.app.Fragment
import com.ispplatform.app.IspApplication
import com.ispplatform.app.data.local.SessionManager
import com.ispplatform.app.ui.auth.LoginActivity
import com.ispplatform.app.util.NetworkUtils

open class BaseFragment : Fragment() {
    protected val session: SessionManager
        get() = (requireActivity().application as IspApplication).sessionManager

    protected fun toast(msg: String) {
        Toast.makeText(requireContext(), msg, Toast.LENGTH_SHORT).show()
    }

    protected fun checkNetwork(): Boolean {
        if (!NetworkUtils.isOnline(requireContext())) {
            toast(getString(com.ispplatform.app.R.string.no_internet))
            return false
        }
        return true
    }

    protected fun handleUnauthorized(code: Int?) {
        if (code == 401) {
            session.clear()
            LoginActivity.startFresh(requireContext())
        }
    }
}
