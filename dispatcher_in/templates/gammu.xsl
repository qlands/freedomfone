<?xml version="1.0" ?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:output method="xml" indent="yes"/> 
 
<xsl:template match="/">
       <event> 
        <Event-Name><xsl:value-of select="event/headers/Event-Name"/></Event-Name>
     <headers>
        <Event-Subclass><xsl:value-of select="event/headers/Event-Subclass"/></Event-Subclass>
        <FreeSWITCH-IPv4><xsl:value-of select="event/headers/FreeSWITCH-IPv4"/></FreeSWITCH-IPv4>
        <Event-Date-Timestamp><xsl:value-of select="event/headers/epoch"/></Event-Date-Timestamp>
        <Proto><xsl:value-of select="event/headers/proto"/></Proto>
	<Login><xsl:value-of select="event/headers/login"/></Login>
        <From><xsl:value-of select="event/headers/FF-SMS-Sender-Number"/></From>
       <body><xsl:value-of select="event/body"/></body>
     </headers>
       </event> 
</xsl:template>
</xsl:stylesheet>
