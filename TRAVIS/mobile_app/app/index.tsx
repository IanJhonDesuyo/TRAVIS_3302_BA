import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import React from 'react';
import { Image, ImageBackground, SafeAreaView, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { APP_ROOT_URL } from '../api/axiosConfig';

const Stat = ({ value, label, icon }: { value: string; label: string; icon: any }) => (
  <View style={s.stat}>
    <Ionicons name={icon} size={17} color="#17234F" />
    <Text style={s.statValue}>{value}</Text>
    <Text style={s.statLabel}>{label}</Text>
  </View>
);

export default function Index() {
  const router = useRouter();

  return (
    <ImageBackground
      source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-municipal-hall.jpg` }}
      style={s.background}
      resizeMode="cover"
    >
      <View pointerEvents="none" style={s.wash} />
      <SafeAreaView style={s.safe}>
        <ScrollView contentContainerStyle={s.content} showsVerticalScrollIndicator={false}>
          <View style={s.live}><View style={s.liveDot} /><Text style={s.liveText}>LIVE · NASUGBU</Text></View>
          <View style={s.brandRow}>
            <Image source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-seal.jpg` }} style={s.seal} />
            <View><Text style={s.brand}>NASUGBU · TMO</Text><Text style={s.brandSub}>Traffic Management Office</Text></View>
          </View>
          <View style={s.badge}><Ionicons name="sparkles" size={13} color="#17234F" /><Text style={s.badgeText}>AI SMART TRAFFIC COMMAND CENTER</Text></View>
          <Text style={s.title}>TRAVIS</Text>
          <Text style={s.subtitle}>An AI, Computer Vision, and IoT-Based Traffic Monitoring and Decision Support System</Text>
          <Text style={s.description}>An intelligent traffic platform that helps monitor violations, congestion, collisions, and road conditions across Nasugbu.</Text>
          <View style={s.stats}><Stat value="Live" label="Active cameras" icon="videocam-outline" /><Stat value="AI" label="Monitoring" icon="hardware-chip-outline" /><Stat value="24/7" label="Decision support" icon="git-network-outline" /></View>
          <TouchableOpacity style={s.loginButton} onPress={() => router.push('/(auth)/login' as Href)}>
            <Ionicons name="log-in-outline" size={19} color="#fff" />
            <Text style={s.loginText}>Personnel Login</Text>
            <Ionicons name="arrow-forward" size={18} color="#fff" />
          </TouchableOpacity>
          <View style={s.footer}><Ionicons name="shield-checkmark-outline" size={14} color="#17304B" /><Text style={s.footerText}>Official municipal traffic information system</Text></View>
        </ScrollView>
      </SafeAreaView>
    </ImageBackground>
  );
}

const s = StyleSheet.create({
  background:{flex:1},safe:{flex:1},wash:{...StyleSheet.absoluteFillObject,backgroundColor:'rgba(238,244,247,.78)'},content:{flexGrow:1,justifyContent:'center',paddingHorizontal:22,paddingTop:22,paddingBottom:30},
  live:{position:'absolute',top:20,right:22,flexDirection:'row',alignItems:'center',gap:6,height:29,paddingHorizontal:11,borderRadius:15,backgroundColor:'rgba(19,45,68,.62)',borderWidth:1,borderColor:'rgba(255,255,255,.26)'},liveDot:{width:6,height:6,borderRadius:3,backgroundColor:'#2ED66F'},liveText:{color:'#fff',fontSize:9,fontWeight:'900',letterSpacing:.7},
  brandRow:{flexDirection:'row',alignItems:'center',marginTop:50,marginBottom:28},seal:{width:50,height:50,borderRadius:25,borderWidth:3,borderColor:'#fff',marginRight:11},brand:{color:'#102F49',fontSize:15,fontWeight:'900'},brandSub:{color:'#526B64',fontSize:10,marginTop:3},
  badge:{alignSelf:'flex-start',flexDirection:'row',alignItems:'center',gap:7,paddingHorizontal:11,paddingVertical:7,borderRadius:18,backgroundColor:'rgba(232,238,244,.86)',borderWidth:1,borderColor:'rgba(23,35,79,.16)'},badgeText:{color:'#17234F',fontSize:9,fontWeight:'900',letterSpacing:.8},
  title:{marginTop:15,color:'#101A43',fontSize:54,lineHeight:58,fontWeight:'900',letterSpacing:-2.5},subtitle:{color:'#17234F',fontSize:17,lineHeight:23,fontWeight:'800'},description:{color:'#405877',fontSize:13,lineHeight:20,marginTop:14},
  stats:{flexDirection:'row',gap:8,marginTop:22},stat:{flex:1,minHeight:82,alignItems:'center',justifyContent:'center',padding:8,borderRadius:14,backgroundColor:'rgba(255,255,255,.82)',borderWidth:1,borderColor:'rgba(23,48,75,.10)'},statValue:{color:'#17234F',fontSize:16,fontWeight:'900',marginTop:3},statLabel:{color:'#5D7090',fontSize:8,textAlign:'center',textTransform:'uppercase',marginTop:3},
  loginButton:{minHeight:54,flexDirection:'row',alignItems:'center',justifyContent:'center',gap:10,marginTop:22,borderRadius:13,backgroundColor:'#102F49',borderBottomWidth:4,borderBottomColor:'#EA9625'},loginText:{color:'#fff',fontSize:15,fontWeight:'800'},footer:{flexDirection:'row',alignItems:'center',justifyContent:'center',gap:6,marginTop:20},footerText:{color:'#17304B',fontSize:10,fontWeight:'600'},
});
