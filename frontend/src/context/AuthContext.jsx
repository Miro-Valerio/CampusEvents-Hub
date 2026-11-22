import React,{createContext,useCallback,useContext,useEffect,useState} from 'react';
import {api,getToken,setToken} from '../lib/api';
const Context=createContext(null);
export function AuthProvider({children}){
  const [user,setUser]=useState(null);
  const [loading,setLoading]=useState(Boolean(getToken()));
  const refresh=useCallback(async()=>{if(!getToken()){setUser(null);setLoading(false);return null;}try{const me=await api('/me');setUser(me);return me;}catch{setToken(null);setUser(null);return null;}finally{setLoading(false);}},[]);
  useEffect(()=>{refresh();const clear=()=>{setToken(null);setUser(null);setLoading(false);};window.addEventListener('spc-unauthorized',clear);return()=>window.removeEventListener('spc-unauthorized',clear);},[refresh]);
  const login=async(email,password)=>{const data=await api('/login',{method:'POST',body:{email,password}});setToken(data.token);setUser(data.user);return data.user;};
  const register=async(body)=>{const data=await api('/register',{method:'POST',body});setToken(data.token);setUser(data.user);return data.user;};
  const logout=async()=>{try{await api('/logout',{method:'POST'});}catch{}setToken(null);setUser(null);};
  return <Context.Provider value={{user,loading,login,register,logout,refresh}}>{children}</Context.Provider>;
}
export const useAuth=()=>useContext(Context);
