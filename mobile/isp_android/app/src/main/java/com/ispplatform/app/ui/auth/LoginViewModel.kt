package com.ispplatform.app.ui.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.ispplatform.app.data.repository.AuthRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class LoginViewModel : ViewModel() {
    private val repo = AuthRepository()
    private val _result = MutableLiveData<Resource<String>>()
    val result: LiveData<Resource<String>> = _result

    fun login(identifier: String, password: String) {
        viewModelScope.launch {
            _result.value = Resource.Loading
            _result.value = repo.login(identifier, password)
        }
    }
}
